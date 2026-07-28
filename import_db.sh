#!/bin/bash

# Create the new database (drop it if it exists so we start fresh)
/Applications/XAMPP/xamppfiles/bin/mysql -u root -e "DROP DATABASE IF EXISTS u355625773_kiuq_system; CREATE DATABASE u355625773_kiuq_system;"

# Import the SQL file into the new database
/Applications/XAMPP/xamppfiles/bin/mysql -u root u355625773_kiuq_system < u355625773_kiuq_system.sql

echo "✅ Database u355625773_kiuq_system has been successfully created and imported!"
