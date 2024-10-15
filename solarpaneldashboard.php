<?php
include('koneksiSolarPanel_starSchema.php');

$inputMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$inputYear = isset($_GET['year']) ? $_GET['year'] : date('Y'); 

$firstLastDateQuery = "
    SELECT 
        MIN(TanggalYMD) AS FirstDate,
        MAX(TanggalYMD) AS LastDate
    FROM 
        datedimension
    WHERE 
        YEAR(TanggalYMD) = :inputYear
        AND PCIMonth = :inputMonth
";

$stmt = $pdo->prepare($firstLastDateQuery);
$stmt->execute(['inputYear' => $inputYear, 'inputMonth' => $inputMonth]);
$dateRange = $stmt->fetch(PDO::FETCH_ASSOC);

$firstDate = $dateRange['FirstDate']; // This will hold the calculated FirstDate
$lastDate = $dateRange['LastDate'];   // This will hold the calculated LastDate

try {
    // Query 1: Total PV output for the specific day (end of the input month)
    $stmtToday = $pdo->prepare("
        WITH RankedData AS (
            SELECT spf2.DateID, spf2.TimeID, spf2.StorageID, spf2.TotalPVOutput,
                   ROW_NUMBER() OVER (PARTITION BY spf2.StorageID ORDER BY td.WaktuHMS DESC) AS rn
            FROM SolarPanelFact spf2
            INNER JOIN DateDimension dd ON spf2.DateID = dd.DateID
            INNER JOIN TimeDimension td ON spf2.TimeID = td.TimeID
            WHERE dd.TanggalYMD = CURRENT_DATE
        )
        SELECT SUM(TotalPVOutput) AS TodayTotalPVOutput
        FROM RankedData
        WHERE rn = 1;
    ");
    $stmtToday->execute();
    $todayTotal = $stmtToday->fetch(PDO::FETCH_ASSOC)['TodayTotalPVOutput'];

    // Query 2: Month-to-date (MTD) total PV output
    $stmtMTD = $pdo->prepare("
        WITH RankedData AS (
                SELECT spf2.DateID, spf2.TimeID, spf2.StorageID, spf2.TotalPVOutput,
                    ROW_NUMBER() OVER (PARTITION BY spf2.DateID, spf2.StorageID ORDER BY td.WaktuHMS DESC) AS rn
                FROM SolarPanelFact spf2
                INNER JOIN DateDimension dd ON spf2.DateID = dd.DateID
                INNER JOIN TimeDimension td ON spf2.TimeID = td.TimeID
                WHERE dd.TanggalYMD BETWEEN :firstDate AND :lastDate
            )
            SELECT SUM(TotalPVOutput) AS MTDValue
            FROM RankedData
            WHERE rn = 1;
    ");
    $stmtMTD->bindParam(':firstDate', $firstDate);
    $stmtMTD->bindParam(':lastDate', $lastDate);
    $stmtMTD->execute();
    $mtdTotal = $stmtMTD->fetch(PDO::FETCH_ASSOC)['MTDValue'];

    // Query 3: Year-to-date (YTD) total PV output
    $stmtYTD = $pdo->prepare("
        WITH RankedData AS (
            SELECT spf2.DateID, spf2.TimeID, spf2.StorageID, spf2.TotalPVOutput,
                ROW_NUMBER() OVER (PARTITION BY spf2.DateID, spf2.StorageID ORDER BY td.WaktuHMS DESC) AS rn
            FROM SolarPanelFact spf2
            INNER JOIN DateDimension dd ON spf2.DateID = dd.DateID
            INNER JOIN TimeDimension td ON spf2.TimeID = td.TimeID
            WHERE dd.TanggalYMD BETWEEN CONCAT(:inputYear, '-01-01') AND :lastDate
        )
        SELECT SUM(TotalPVOutput) AS YTDValue
        FROM RankedData
        WHERE rn = 1;
    ");
    $stmtYTD->bindParam(':inputYear', $inputYear);
    $stmtYTD->bindParam(':lastDate', $lastDate);
    $stmtYTD->execute();
    $ytdTotal = $stmtYTD->fetch(PDO::FETCH_ASSOC)['YTDValue'];

    // Query 4: Emission, Deforestation, Coal for the month
    $stmtEmission = $pdo->prepare("
    WITH RankedData AS (
                SELECT spf2.DateID, spf2.TimeID, spf2.StorageID, spf2.Emission, spf2.Deforestation, spf2.Coal,
                    ROW_NUMBER() OVER (PARTITION BY spf2.DateID, spf2.StorageID ORDER BY td.WaktuHMS DESC) AS rn
                FROM SolarPanelFact spf2
                INNER JOIN DateDimension dd ON spf2.DateID = dd.DateID
                INNER JOIN TimeDimension td ON spf2.TimeID = td.TimeID
                WHERE dd.TanggalYMD BETWEEN :firstDate AND :lastDate
            )
            SELECT 
                SUM(Emission) AS MTD_Emission,
                SUM(Deforestation) AS MTD_Deforestation,
                SUM(Coal) AS MTD_Coal
            FROM RankedData
            WHERE rn = 1;
    ");
    $stmtEmission->bindParam(':firstDate', $firstDate);
    $stmtEmission->bindParam(':lastDate', $lastDate);
    $stmtEmission->execute();
    $emissionResult = $stmtEmission->fetch(PDO::FETCH_ASSOC);
    $totalEmission = $emissionResult['MTD_Emission'];
    $totalDeforestation = $emissionResult['MTD_Deforestation'];
    $totalCoal = $emissionResult['MTD_Coal'];

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<?php
// Include the database connection file
include('koneksiSolarPanel_starSchema.php');

// Get the input month and year from the form submission (via GET method)
$inputMonth = isset($_GET['month']) ? $_GET['month'] : date('m'); // Default to current month if not set
$inputYear = isset($_GET['year']) ? $_GET['year'] : date('Y');    // Default to current year if not set

try {
    // Prepare the SQL query with dynamic month and year inputs
    $sql = "
        WITH RankedData AS (
            SELECT spf2.DateID, spf2.TimeID, spf2.StorageID, sd.Building, spf2.TotalPVOutput,
                ROW_NUMBER() OVER (PARTITION BY spf2.StorageID, spf2.DateID ORDER BY td.WaktuHMS DESC) AS rn
            FROM SolarPanelFact spf2
            INNER JOIN DateDimension dd ON spf2.DateID = dd.DateID
            INNER JOIN TimeDimension td ON spf2.TimeID = td.TimeID
            INNER JOIN StorageDimension sd ON spf2.StorageID = sd.StorageID
            WHERE dd.PCIMonth = :inputMonth
            AND YEAR(dd.TanggalYMD) = :inputYear
        )
        SELECT dd.TanggalYMD, COALESCE(SUM(rd.TotalPVOutput), 0) AS DailyTotalPVOutput
        FROM DateDimension dd
        LEFT JOIN RankedData rd ON dd.DateID = rd.DateID AND rd.rn = 1
        WHERE dd.PCIMonth = :inputMonth
        AND YEAR(dd.TanggalYMD) = :inputYear
        GROUP BY dd.TanggalYMD
        ORDER BY dd.TanggalYMD;
    ";

    // Prepare the statement
    $stmt = $pdo->prepare($sql);

    // Bind the parameters for the input month and year
    $stmt->bindParam(':inputMonth', $inputMonth);
    $stmt->bindParam(':inputYear', $inputYear);

    // Execute the query
    $stmt->execute();

    // Fetch all results
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Output data as JSON for the frontend
    $data_json = json_encode($data);
    
    $monthInput = isset($_GET['month']) ? (int)$_GET['month'] : date('n'); // Default to the current month if no input

    // Array to map the month numbers to month names
    $months = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December'
    ];

    // Get the month name and convert it to uppercase
    $monthDisplay = strtoupper($months[$monthInput]);

} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="refresh" content="300">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solar Panel Dashboard</title>
    <script src="chart.js"></script>
    <style>
        body { 
            background-color : #F8F8F8;
        }
        .summary-box {
            display: flex;
            width: 29%;
            height: 85px;
            margin: 10px;
            padding: 20px;
            background-color: #ffffff;
            align-items: center;
            border: 1px solid #4c97ed;
            box-shadow: 5px 5px 8px rgba(0, 0, 0, 0.3);
        }
        
        .box-content{
            display: flex;
            justify-content: space-between;
            gap: 60px;
            align-items: center;
            max-width: fit-content;
            margin-left: auto;
            margin-right: auto;
        }
        .circle-text-wrapper{
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .circle {
            width: 100px;
            height: 100px;
            background-color: #4cdc8d;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Adjust based on viewport dimensions to maintain the circle shape */
        @media (max-width: 1248px) {
            .circle {
                width: min(100px, 10vw);
                height: min(100px, 10vw);
            }
        }

        @media (max-height: 227px) {
            .circle {
                width: min(100px, 10vh);
                height: min(100px, 10vh);
            }
        }


        .circle-text {
            font-size: 20px;
            color: white;
            font-weight: bold;
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
            margin-top: 10px;
        }
        .summary-wrapper{
            display: flex;
            justify-content: center;
        }
       .break {
            flex-basis: 100%;
            height: 0;
        }
        .summary-container {
            display: flex;
            width: 99%;
            box-sizing: border-box;
            justify-content: space-around;
            margin-bottom: 5px;
        }
        .summary-box-text{
            justify-content: center;
        }
        .details-container {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
        }
        .canvas-wrapper{
            display: flex;
            justify-content: center;
            height: auto;
        }
        .canvas-container{
            width: 97%;
            background-color: #Ffffff;
            border: 1px solid #4c97ed;
            padding: 20px;
            box-sizing: border-box;
            box-shadow: 5px 5px 8px rgba(0, 0, 0, 0.3);
            height: auto;
        }
        .summary-box-text h2 {
            color: #4c74ed;
            font-family: Arial, Helvetica, sans-serif;
        }
        .summary-box-text h3 {
            color: #91959e;
            font-family: Arial, Helvetica, sans-serif;
            text-align: center;
            font-size: 14px;
            font-weight: bold;
        }

        .summary-box-text .coal-formula,
        .summary-box-text .tree-formula,
        .summary-box-text .co2-formula
        {
            font-size: 12px !important;
        }
     
        .title{
            display: flex;
            justify-content: center;
            margin-left: auto;
        }
        .title h1{
            font-size: 25px;
            color: #4c74ed;
            font-family: Arial, Helvetica, sans-serif;
        }
        .ytd-contribution-container{
            margin-top: 10px;
            display: flex;
            justify-content: center;
            margin-bottom: 0px;
        }
        .ytd-contribution-box{
            width: 97%;
            background-color: #ffffff;
            box-shadow: 5px 5px 8px rgba(0, 0, 0, 0.3);
            box-sizing: border-box;
            border: 1px solid #4c97ed;
        }
        .ytd-contribution-box h2{
            text-align: center; 
            color: #4c74ed;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 20px
        }

        img{ 
            display: flex;
            margin-right: 30px;
        }

        .co2{
            height: 70px;
            width: auto;
        }

        .tree{
            width: 70px;
        }

        .coal{
            height: 90px;
            width: auto;
        }

        .co2-text{
            color: #4c74ed!important;
            margin-top: 15px;
            font-size: 20px;
        }

        .tree-text{
            color: #4c74ed !important;
            margin-top: 15px;
            font-size: 20px;
        }

        .coal-text{
            margin-left: 25px;
            color: #4c74ed !important;
            margin-top: 15px;
            font-size: 20px;
        }
        .social-contribution{
            color: #000000;
        }

        .summary-box-text p{
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            color: #4cdc8d;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 20px;
        }
        sub {
            font-size: 15px;
        }
        .title-container{
            display: flex;
            align-items: center;
            position: relative;
            justify-content: center;
        }

        .date-container{
            display: flex;
            align-items: center;
            justify-content: flex-end;
            margin-left: auto;
            margin-right: 30px;
            font-weight: bold;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 15px;
        }
        .date-picker-container{
            display: flex;
            align-items: center;
        }
        .canvas-title-container{
            display: flex;
            justify-content: space-between;
            padding: 0 20px;
        }

        .canvas-container h1{
            text-align: center;
            font-weight: bold; 
            font-size: 25px;
            color: #4c74ed;
            font-family: Arial, Helvetica, sans-serif;
        }
        .canvas-box{
            display: flex;
            justify-content: center;
            width: 100%;
        }
        .small-text {
            text-align: center;
            font-weight: normal;
            font-size: 13px;
        }

    </style>
</head>
<body>
    <div class='title-container'>
        <div class='title'>
            <h1>SOLAR GREEN ENERGY DASHBOARD (<?php echo $monthDisplay; ?>)</h1>
        </div>
        <div class='date-container'>
            <?php echo date("Y-m-d")?>
        </div>
    </div>
    <div class='summary-wrapper'>
        <div class="summary-container">
        <div class="summary-box">
                <div class="box-content">
                    <div class="circle">
                        <div class='circle-text-wrapper'>
                            <div class="circle-text" id="circleText">
                                <?php echo number_format(($todayTotal), 2); ?><br>
                                <span class="small-text">kWh</span>
                            </div>
                        </div>
                    </div>
                    <div class='summary-box-text'>
                        <h2>Today's Power Generated</h2>
                    </div>
                </div>
            </div>
            <div class="summary-box">
                <div class="box-content">
                    <div class="circle">
                        <div class='circle-text-wrapper'>
                            <div class="circle-text" id="circleText">
                                <?php echo number_format(($mtdTotal), 2); ?><br>
                                <span class="small-text">kWh</span>
                            </div>
                        </div>
                    </div>
                    <div class='summary-box-text'>
                        <h2>MTD Power Generated</h2>
                    </div>
                </div>
            </div>
            <div class="summary-box">
                <div class="box-content">
                    <div class="circle">
                        <div class='circle-text-wrapper'>
                            <div class="circle-text" id="circleText">
                                <?php echo number_format(($ytdTotal), 2); ?><br>
                                <span class="small-text">kWh</span>
                            </div>
                        </div>
                    </div>
                    <div class='summary-box-text'>
                        <h2>YTD Power Generated</h2>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class='canvas-wrapper'>
        <div class='canvas-container'>
            <div class='canvas-title-container'>
                <h1>MTD Green Energy Generated Per-Day (kWh) </h1>
                    <div class='date-picker-container'>
                    <form method="GET" action="solarpaneldashboard.php">
                        <label for="month">Select Month:</label>
                            <select name="month" id="month">
                                <?php
                                $currentMonth = $inputMonth; // Use $inputMonth instead of date('m')
                                $months = [
                                    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
                                    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
                                    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
                                ];

                                foreach ($months as $key => $name) {
                                    $selected = ($key == $currentMonth) ? 'selected' : '';
                                    echo "<option value='$key' $selected>$name</option>";
                                }
                                ?>
                            </select>
                        <label for="year">Select Year:</label>
                        <input type="number" name="year" id="year" value="<?php echo $inputYear; ?>" min="2000" max="<?php echo date('Y'); ?>">
                        <button type="submit">Update</button>
                    </form>
                </div>
            </div>
            <div class='canvas-box'>
                <canvas id="solarChart" display="flex" height="370" width="1700"></canvas>
            </div>
        </div>
    </div>
    <div class='ytd-contribution-container'>
        <div class='ytd-contribution-box'>
            <h2>MTD Social Contribution</h2>
        </div>
    </div>
    
    <div class='summary-wrapper'>
        <div class="summary-container">
            <div class="summary-box">
                <div class="box-content">
                    <div class="pict">
                        <img src="co2.jpg" class='co2' alt="CO2">
                    </div>
                    <div class='summary-box-text'>
                        <h3 class='co2-text'>CO₂ Emission Reduction</h2>
                        <p><?php echo number_format($totalEmission, 2); ?> &nbsp<sub> KG</sub></p>
                        <h3 class='co2-formula'>(1 kWh = 0.02 CO₂)</h3>
                    </div>
                </div>
            </div>
            <div class="summary-box">
                <div class="box-content">
                    <div class="pict">
                        <img src="tree.jpg" class='tree' alt="Tree">
                    </div>
                    <div class='summary-box-text'>
                        <h3 class='tree-text'>Reduced Deforestation</h2>
                        <p><?php echo number_format($totalDeforestation, 2); ?>&nbsp<sub> TREE</sub></p>
                        <h3 class='tree-formula'>(1 Tree = CO₂ kg / 11)</h3>
                    </div>
                </div>
            </div>
            <div class="summary-box">
                <div class="box-content">
                    <div class="pict">
                        <img src="tong.jpg" class='coal' alt="Coal">
                    </div>
                    <div class='summary-box-text'>
                        <h3 class='coal-text'>Standard Coal Saved</h2>
                        <p><?php echo number_format($totalCoal, 2); ?>&nbsp<sub> KG</sub></p>
                        <h3 class='coal-formula'>(1 kg Coal Saved = CO₂ kg / 1.48)</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const data = <?php echo $data_json; ?>;

        // Assuming 'data' is the JSON object containing the results from your PHP query
        if (data && data.length > 0) {
        // Extract the day part from the date 'YYYY-MM-DD'
        const labels = data.map(item => item.TanggalYMD.slice(-2)); // Extract 'DD' from 'YYYY-MM-DD'
        const values = data.map(item => item.DailyTotalPVOutput);  // Use the correct field

        const ctx = document.getElementById('solarChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',  // Bar chart
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total PV Output per Day',
                    data: values,
                    backgroundColor: '#4cdc8d',  // Bar color
                    borderColor: 'rgba(75, 192, 192, 1)',  // Border color
                    borderWidth: 1
                }]
            },
            maintainAspectRatio: false,
            options: {
                responsive: true,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Day',  // X-axis label
                            font: {
                                size: 16  // Increase label font size
                            },
                            padding: {
                                top: 10,  // Increase padding between title and ticks
                                bottom: 10
                            }
                        },
                        ticks: {
                            padding: 15,  // Add more padding between ticks
                            font: {
                                size: 14  // Increase tick label font size
                            }
                        },
                        barPercentage: 0.5,  // Bar width
                        categoryPercentage: 1.0  // Bar category width
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Total PV Output'  // Y-axis label
                        },
                        beginAtZero: true  // Start y-axis at 0
                    }
                }
            }
        });
    } else {
        // Handle case where there is no data
    }

    </script>
</body>
</html>