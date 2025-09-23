# Forecast Sheets Plugin Updates

## Recent Changes

### 1. Individual Customer Forecasts
- Redesigned the forecast pages for individual customers to show all months of a year at once
- Added year toggle functionality to easily switch between different years
- Updated export functionality to include all months in the same email/CSV

### 2. Unified Totals Dashboard
- Combined month and quarter views into a single unified dashboard
- Added toggle options to filter by year, quarter, or month
- Auto-selects the current period but allows switching to past periods
- Updated export functionality to match the new display format
- Removed the separate month and quarter dashboard pages

## File Changes

### New Files
- `dashboard-totals-unified.php`: New unified totals dashboard with year/quarter/month toggle
- `js/unified-totals.js`: JavaScript for the unified totals dashboard

### Modified Files
- `dashboard-individuals.php`: Updated to show all months at once with year toggle
- `forecast-sheets.php`: Updated to include only the unified dashboard and remove the old separate dashboards

### Removed References
- Removed separate month-based totals page
- Removed separate quarter-based totals page

## Usage Instructions

### Individual Customer Forecasts
1. Navigate to the individual customer forecast page
2. Use the year dropdown to select the desired year
3. All months for the selected year will be displayed in a single table
4. Use the export buttons to send the forecast data via email or download as CSV

### Unified Totals Dashboard
1. Navigate to the Forecast Totals dashboard
2. Use the filter type dropdown to select between year, quarter, or month view
3. Use the corresponding filter dropdown to select the specific period
4. Use the export buttons to send the data via email or download as CSV

## Technical Details

### Database Structure
The plugin uses two main tables:
- `wp_forecast_sheets`: Stores individual customer forecasts
- `wp_forecast_sheets_totals`: Stores aggregated totals data

No database structure changes were required for these updates.

### JavaScript Dependencies
- jQuery
- DataTables
- DataTables Responsive

### Admin Menu Structure
The admin menu structure has been simplified to include only the unified Forecast Totals dashboard instead of separate month and quarter views.
