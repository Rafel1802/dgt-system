#!/bin/bash

# Create the new database (drop it if it exists so we start fresh)
/Applications/XAMPP/xamppfiles/bin/mysql -u root -e "DROP DATABASE IF EXISTS u768808434_dgt_system; CREATE DATABASE u768808434_dgt_system;"

# Import the SQL file into the new database
/Applications/XAMPP/xamppfiles/bin/mysql -u root u768808434_dgt_system < u768808434_dgt_system.sql

echo "✅ Database u768808434_dgt_system has been successfully created and imported!"
