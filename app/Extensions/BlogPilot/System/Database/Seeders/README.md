# BlogPilot Agent Demo Data Seeder

This seeder creates fake agents and demo posts for testing the BlogPilot Agent extension.

## Usage

Run the seeder using the Artisan command:

```bash
php artisan blogpilot:seed-demo-data
```

## Cleanup

To remove demo data, manually delete agents from the UI or database:

- Delete agents from `ext_blogpilot` table
- Reset posts from `blogs` table (only `is_blogpilot` column is `1`)
