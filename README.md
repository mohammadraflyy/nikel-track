# Vehicle Booking System - Laravel

## Installation

### Prerequisites
- PHP 8.2+
- Composer 2.8.6+
- MySQL 5.7+ or MariaDB 10.4.32+
- Node.js 14+

### Setup Steps

1. \*\*Clone repository\*\*
   \`\`\`bash
   git clone https://github.com/mohammadraflyy/nikel-track.git
   cd vehicle-booking-system
   \`\`\`

2. \*\*Install dependencies\*\*
   \`\`\`bash
   composer install
   npm install
   \`\`\`

3. \*\*Configure environment\*\*
   \`\`\`bash
   cp .env.example .env
   nano .env  # Edit database credentials
   \`\`\`

4. \*\*Generate keys\*\*
   \`\`\`bash
   php artisan key:generate
   \`\`\`

5. \*\*Database setup\*\*
   \`\`\`bash
   php artisan migrate --seed
   \`\`\`

6. \*\*Build assets\*\*
   \`\`\`bash
   npm run build
   \`\`\`

7. \*\*Run server\*\*
   \`\`\`bash
   composer run dev
   \`\`\`

## Database Schema

| Table          | Key Fields                          | Relationships               |
|----------------|-------------------------------------|-----------------------------|
| users          | id, name, email, password          | bookings, approvals         |
| vehicles       | id, license_plate, type, status    | bookings, fuel_logs         |
| drivers        | id, name, license_number, status   | bookings                    |
| bookings       | id, user_id, vehicle_id, status    | approvals, usage_logs       |
| approvals      | id, booking_id, approver_id, level | booking, users              |
| system_logs    | id, user_id, action, table_name    |                             |
| fuel_logs      | id, vehicle_id, amount, log_date   | vehicles                    |
| service_logs   | id, vehicle_id, service_date, cost | vehicles                    |
| usage_logs     | id, booking_id, start_km, end_km   | bookings                    |

## Seeded Data

Default admin account:
- Email: admin@nikeltrack.com  
- Password: Admin@1234

Approver Level 1 account:
- Email: approver1@nikeltrack.com  
- Password: Approver1@1234

Approver Level 2 account:
- Email: approver2@nikeltrack.com  
- Password: Approver2@1234

Sample data includes:
- 5 vehicles
- 5 drivers
- 3 test users