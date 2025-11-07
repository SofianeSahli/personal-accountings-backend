#!/bin/bash

# This script helps migrate MySQL data to PostgreSQL
# Requirements: mysqldump, pg_dump, psql

# Export MySQL data
echo "Exporting MySQL data..."
mysqldump -h localhost -u root -p expense_management > mysql_dump.sql

# Convert MySQL syntax to PostgreSQL
echo "Converting MySQL syntax to PostgreSQL..."
sed -i 's/AUTO_INCREMENT/SERIAL/g' mysql_dump.sql
sed -i 's/ENGINE=InnoDB//g' mysql_dump.sql
sed -i 's/DEFAULT CHARSET=utf8mb4//g' mysql_dump.sql
sed -i 's/COLLATE=utf8mb4_unicode_ci//g' mysql_dump.sql
sed -i 's/`//g' mysql_dump.sql
sed -i 's/UNSIGNED //g' mysql_dump.sql

# Import to PostgreSQL
echo "Importing to PostgreSQL..."
psql -h localhost -U postgres -d expense_management < mysql_dump.sql

echo "Migration completed!"