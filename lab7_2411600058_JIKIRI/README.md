# Automated Inventory Replenishment and Supplier Management System

A Laravel-based inventory and procurement management application developed for Laboratory Exercise 7. The project extends an inventory system with supplier management, purchase order processing, automated reorder detection, approval workflows, email notifications, supplier performance reporting, and procurement dashboard widgets.

## Project Overview

The system helps users monitor inventory levels and manage the purchasing process. It stores supplier information, tracks purchase orders, and checks whether product stock has reached its reorder point. When products need replenishment, the system can prepare draft purchase orders grouped by supplier for review before submission.

The application also provides role-based access, purchase order approval records, receiving functions, and reports that help users monitor supplier delivery performance.

## Main Features

### 1. Supplier Management
- Create, view, update, and manage supplier records.
- Store supplier contact information, payment terms, lead time, and minimum order amount.
- Link suppliers to products, including supplier SKU, unit cost, minimum order quantity, pack size, and preferred-supplier information.
- Use soft deletes to retain supplier records referenced by existing purchase orders.
- View supplier performance information.

### 2. Purchase Order Management
- Create and manage purchase orders with unique reference numbers.
- Add products, quantities, and unit prices to purchase order line items.
- Calculate subtotals, tax, and order totals.
- Track order statuses such as draft, pending, approved, rejected, sent, received, and cancelled.
- Record approval history and receiving activity.
- Support partial receiving by recording quantities received against individual order items.

### 3. Approval and Authorization
- Use Laravel policies to control purchase order access and actions.
- Separate permissions for administrators, managers, and warehouse users.
- Prevent managers from approving purchase orders they created.
- Support an additional administrator approval for orders that reach the configured approval threshold.

### 4. Automated Inventory Replenishment
- Calculate average daily usage using recent inventory-out transactions.
- Calculate reorder points using product usage, supplier lead time, and safety stock, while respecting the product's configured reorder level.
- Consider stock already on committed purchase orders.
- Calculate suggested order quantities while considering supplier minimum quantities and pack sizes.
- Group products by supplier and create draft purchase orders for products that need replenishment.
- Avoid generating another draft for a product that is already covered by an existing draft.

### 5. Email and System Notifications
- Use Laravel Mailable classes for purchase order and low-stock notifications.
- Queue supported email notifications for background processing.
- Attach a PDF copy of a purchase order to the purchase-order-created notification.
- Notify administrators and managers when automated reorder drafts are generated.

### 6. Procurement Dashboard
The dashboard includes reusable widgets for:
- Pending purchase orders.
- Recent purchase orders.
- Supplier performance.
- Products requiring replenishment.

Dashboard widget data is supplied through Laravel View Composers and cached to reduce repeated database queries.

### 7. Supplier Performance Reporting
- Summarize supplier order activity and total order value.
- Calculate on-time delivery percentages.
- Compare actual and expected delivery periods.
- Track order accuracy information where receiving records are available.
- Display supplier performance summaries and delivery trends.

### 8. Search, Filtering, and Exports
- Search and filter purchase orders and supplier records.
- Filter records by relevant fields such as status, supplier, and date range.
- Export supported datasets in CSV and PDF formats.
- Export individual purchase order documents as PDF files.

## Technology Stack

- **Backend:** PHP 8.2+ and Laravel 12
- **Database:** MySQL / MariaDB
- **Frontend:** Blade templates, HTML, CSS, JavaScript, and Bootstrap-based styling
- **Authentication:** Laravel authentication
- **Database access:** Laravel Eloquent ORM
- **Notifications:** Laravel Notifications and Mailables
- **Background processing:** Laravel Queue
- **Scheduling:** Laravel Task Scheduling
- **PDF generation:** Laravel DomPDF integration

## User Roles

The application uses role-based authorization. The main roles include:

- **Administrator:** manages procurement operations and performs administrative approval.
- **Manager:** manages suppliers and purchase orders, submits orders, and performs permitted approval actions.
- **Warehouse:** views purchase orders and records received goods.
- **Staff:** has access limited by the application's assigned permissions.

The actions available to a user depend on the authorization rules enforced by the application.

## Installation and Setup

### Requirements

Install the following before setting up the project:

- PHP 8.2 or later
- Composer
- MySQL or MariaDB
- Node.js and npm
- A local development environment such as XAMPP

### 1. Clone the repository

```bash
git clone <your-github-repository-url>
cd <your-project-folder>
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install frontend dependencies

```bash
npm install
```

### 4. Configure the environment

Copy the example environment file:

```bash
copy .env.example .env
```

Update the database settings in `.env` to match your local MySQL configuration:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password
```

Configure the mail settings and queue connection according to the environment in which the application will run. Do not commit your personal `.env` file.

### 5. Generate the application key

```bash
php artisan key:generate
```

### 6. Run database migrations and seeders

Create the database in MySQL first, then run:

```bash
php artisan migrate --seed
```

### 7. Build frontend assets

```bash
npm run build
```

### 8. Start the application

```bash
php artisan serve
```

Open the local address shown by Artisan in your browser.

## Useful Artisan Commands

Check products that need replenishment without creating purchase orders:

```bash
php artisan reorder:check --dry-run
```

Run the reorder check and generate draft purchase orders when applicable:

```bash
php artisan reorder:check
```

View scheduled tasks:

```bash
php artisan schedule:list
```

For a local development environment, process queued jobs using:

```bash
php artisan queue:work
```

Clear cached application configuration when environment settings change:

```bash
php artisan optimize:clear
```

## Automated Reorder Schedule

The reorder check is registered in `routes/console.php` and is scheduled to run daily at 6:00 AM. The schedule uses `withoutOverlapping()` to prevent a second instance from starting while an earlier run is still active.

On a production server, Laravel's scheduler should be triggered by the server's cron service once every minute using the Laravel `schedule:run` command.

## Important Project Notes

- The `.env` file contains local environment settings and must not be uploaded to a public repository.
- Do not commit `vendor`, `node_modules`, local database files, logs, or generated cache files.
- Review the configured database, mailer, and queue settings before running the application in another environment.
- Automated replenishment creates draft purchase orders for review; users should check the supplier, quantities, and order details before submitting them.
- The approval threshold is configurable through the procurement configuration and environment settings.

## Project Structure

```text
app/
├── Console/Commands/
├── Exports/
├── Http/Controllers/
├── Mail/
├── Models/
├── Notifications/
├── Observers/
├── Policies/
├── Providers/
└── Services/

database/
├── migrations/
└── seeders/

resources/
└── views/
    ├── dashboard/
    ├── emails/
    ├── purchase-orders/
    ├── reports/
    ├── suppliers/
    └── widgets/

routes/
├── console.php
└── web.php
```

## Project Purpose

This project demonstrates how Laravel's MVC architecture and supporting features can be used to build an inventory and procurement application. It combines database relationships, business logic services, authorization policies, scheduled commands, notifications, reusable Blade views, caching, and reporting into one system.

Developed as an academic laboratory exercise on advanced MVC architecture with Laravel.
