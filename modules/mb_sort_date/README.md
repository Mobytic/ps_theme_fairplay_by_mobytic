# MB Sort Date Module

## Description
This PrestaShop module adds sorting options by product availability date (ascending and descending) for selected categories.

## Features
- Select one or multiple categories in backoffice where sort by date should be enabled
- Add two sort options:
  - Availability Date: Oldest first (ascending)
  - Availability Date: Newest first (descending)
- Sorts products by the `available_date` field from `ps_product_stock_available` table

## Installation
1. Upload the module folder to `/modules/`
2. Go to Back Office > Modules > Module Manager
3. Search for "Sort by Availability Date"
4. Click "Install"
5. Configure the module to select categories

## Configuration
1. Go to Modules > Module Manager
2. Find "Sort by Availability Date" and click "Configure"
3. Select the categories where you want to enable availability date sorting
4. Click "Save"

## Usage
Once configured, customers will see two new sort options in the selected category pages:
- "Availability Date: Oldest first" - Shows products with the oldest availability dates first
- "Availability Date: Newest first" - Shows products with the newest availability dates first

## Technical Details
- Uses PrestaShop's product search hooks to add custom sort orders
- Integrates with the native product listing system
- Sorts by `ps_product_stock_available.available_date` field
- Compatible with PrestaShop 1.7+

## Author
Mobytic

## Version
1.0.0

## License
Academic Free License (AFL 3.0)
