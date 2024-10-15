# Dashboard for Displaying Solar Panel Energy Using Node-RED Cloud Extraction

This project sets up a dashboard to display the energy generated from a solar panel system. The data is extracted using **Node-RED** integrated with **Growatt Cloud** and visualized through a **PHP** web interface.

## 🚀 Features
- **Real-time energy data** from solar panels, such as total PV output, grid import, and consumption power.
- Integration with **Node-RED** for handling cloud data extraction from Growatt.
- **PHP dashboard** for user-friendly data visualization.
- Customizable display for metrics like today’s data, month-to-date (MTD), and year-to-date (YTD) solar power generation.

## 🛠️ Technologies
- **Node-RED**: A flow-based development tool to automate data extraction from Growatt Cloud.
- **Growatt Cloud API**: Cloud-based service to pull solar panel performance data.
- **PHP**: For creating a dynamic web interface to display extracted data.
- **MySQL**: Used to store extracted data for visualization and reporting.
- **JavaScript**: For enhancing interactivity on the dashboard (if needed).
  
## 🌐 Architecture Overview
1. **Node-RED Cloud Extraction**: Node-RED is used to fetch real-time data from Growatt Cloud using API integration.
2. **PHP Frontend**: The PHP dashboard is designed to visualize the extracted data, with graphs and metrics like daily, monthly, and yearly solar output.
3. **MySQL Database**: Stores the solar panel data fetched by Node-RED for historical analysis and efficient querying.

## 🔧 Setup Instructions

### Prerequisites
- Install Node.js and Node-RED on your local server.
- A Growatt Cloud account with API access credentials.
- PHP installed on a server for the dashboard.
- MySQL database setup for storing energy data.

### Step 1: Set Up Node-RED for Growatt Cloud Extraction
1. Install Node-RED by following [these instructions](https://nodered.org/docs/getting-started/local).
2. Install required Node-RED modules:
   ```bash
   npm install node-red-contrib-growatt
