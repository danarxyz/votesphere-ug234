# VoteSphere - Modern Polling Application

A feature-rich polling application built with PHP 8.4, Alpine.js, and Tailwind CSS.

## Features
- Create and manage polls
- Real-time voting
- Comments system
- Export to CSV/PDF
- Dark mode support
- Responsive design

## Requirements
- PHP 8.4+
- MySQL 8.0+
- Composer

## Installation
1. `composer install`
2. Import `config/schema.sql`
3. Configure `database/db_config.php`
4. Start server: `php -S localhost:8000`

## File Structure
```
src/          # Application logic
config/       # Configuration files
database/     # Database connection
utils/        # Helper functions
includes/     # Common templates
public/       # Public entry point
```