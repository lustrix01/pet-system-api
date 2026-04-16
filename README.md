# Pet Management System - Backend API

Hey team! This repository holds our Group REST API Enhancement project. I have initialized the repository with our core backend architecture.

### System Overview Plan

The goal of this project is to build a centralized REST API that can communicate with multiple different client applications.

Our planned architecture:

- **The Backend (Server):** A PHP-based REST API that connects to a MySQL database. It will handle user authentication and CRUD operations for pet records.
- **Client 1 (PHP):** A server-side Customer Panel where users can log in using sessions and manage their own pets.
- **Client 2 (JavaScript/HTML):** A client-side Admin Dashboard using the Fetch API to view and manage all users and pets in the system.

### Current Backend Files

I have set up the initial backend files to get us started:

- **`db.php` (Database Integration):**
  - Connects to the local MySQL database named `user_system`.
  - Includes built-in error handling. If the database connection drops, it will output a clean JSON error (HTTP 500) instead of crashing the PHP script.

- **`api.php` (REST API Logic):**
  - This is our main controller. It forces all responses into strict JSON format with proper HTTP headers.
  - Includes a custom `respond()` function to easily send back standard HTTP status codes (200, 201, 400, 409, 500).
  - **Currently Implemented:** 
      - The `register` endpoint. It accepts a username and password, checks if the user already exists, hashes the password for security, and saves it to the database.
      - The `login` endpoint. It accepts a username and password, checks if both fields match its corresponding record in the database, and responds accordingly: (1) for **empty field/s**, returns an error; (2) if **user does not exist**, it returns an error; (3) if the **password is incorrect**, it blocks login and returns an error; (4) if **username and password match** the ones in the database, it returns a success response.
  
