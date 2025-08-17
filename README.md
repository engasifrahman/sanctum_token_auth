<div align="center">
  <h1>Laravel 12 API Auth System with Sanctum 🔑</h1>
</div>

This project is a robust API system built on **Laravel 12** that uses **Laravel Sanctum** for token-based authentication. It follows a monorepo folder structure 📂 and includes a comprehensive Docker setup 🐳 for **local (dev)**, **staging**, and **production** environments. The codebase is held to the highest standards, with **100% PSR-12** compliance ✅ and **100% test coverage** ✅ using PHPUnit 🧪.

-----

## ✨ Key Features

  - **Authentication:** Token-based authentication using **Laravel Sanctum**. 🛡️
  - **User Management:** Routes for user registration ✍️, login 🚪, password management ⚙️, and email verification 📧.
  - **Access Control:** Implements role-based access with custom middleware 👮, ensuring different user types (`Admin`, `User`, `Subscriber`) have the correct access.
  - **Dockerized Environment:** Separate Docker images are provided for **local (dev)** 🛠️, **staging**, and **production** 🚀, allowing for a consistent, isolated, and optimized environment.
  - **Code Quality:**
      - **100% PSR-12** compliant code style. ✅
      - **100% test coverage** verified by both unit and feature tests. ✅
      - **Static analysis** with **Larastan** 🧐 to find potential bugs and code smells early.
  - **Continuous Integration:** Four GitHub Actions workflows are configured to automate checks for every pull request:
    1.  **PHPUnit Test with Coverage Check:** Runs the full test suite 🧪 and verifies code coverage percentage 📊.
    2.  **Migration Check:** Ensures database migrations are valid and can be run. 💾
    3.  **PHP-CS-Fixer Check:** Automatically checks and fixes code style to maintain PSR-12 compliance. 🎨
    4.  **Larastan Check:** Performs static code analysis to catch common issues. 🚦

-----

## 📂 Project Structure

This project follows a monorepo structure with a clear and logical directory layout. The main application code lives within the `src` folder, while all Docker-related files and configurations are in the `docker` directory.

```bash
.
📦sanctum_token_auth
 ┣ 📂.github
 ┃ ┗ 📂workflows
 ┃ ┃ ┣ 📜database-migration-check.yml
 ┃ ┃ ┣ 📜larastan.yml
 ┃ ┃ ┣ 📜php-cs-fixer.yml
 ┃ ┃ ┣ 📜phpunit.yml
 ┣ 📂.vscode
 ┃ ┗ 📜launch.json
 ┣ 📂docker
 ┃ ┣ 📂nginx
 ┃ ┃ ┣ 📂html
 ┃ ┃ ┃ ┗ 📜maintenance.html
 ┃ ┃ ┗ 📂templates
 ┃ ┃ ┃ ┗ 📜default.conf.template
 ┃ ┗ 📂php-fpm
 ┃ ┃ ┣ 📜Dockerfile
 ┃ ┃ ┣ 📜entrypoint.sh
 ┃ ┃ ┗ 📜supervisor.conf
 ┣ 📂src
 ┃ ┣ 📂app
 ┃ ┃ ┣ 📂Http
 ┃ ┃ ┃ ┣ 📂Controllers
 ┃ ┃ ┃ ┣ 📂Middleware
 ┃ ┃ ┃ ┗ 📂Requests
 ┃ ┃ ┣ 📂Logging
 ┃ ┃ ┣ 📂Mixins
 ┃ ┃ ┣ 📂Models
 ┃ ┃ ┗ 📂Providers
 ┃ ┣ 📂bootstrap
 ┃ ┣ 📂config
 ┃ ┣ 📂coverage-html
 ┃ ┣ 📂database
 ┃ ┃ ┣ 📂factories
 ┃ ┃ ┣ 📂migrations
 ┃ ┃ ┣ 📂seeders
 ┃ ┃ ┗ 📜.gitignore
 ┃ ┣ 📂lang
 ┃ ┣ 📂public
 ┃ ┣ 📂resources
 ┃ ┣ 📂routes
 ┃ ┣ 📂storage
 ┃ ┃ ┣ 📂app
 ┃ ┃ ┃ ┣ 📂public
 ┃ ┃ ┣ 📂framework
 ┃ ┃ ┗ 📂logs
 ┃ ┣ 📂tests
 ┃ ┃ ┣ 📂Feature
 ┃ ┃ ┣ 📂Unit
 ┃ ┣ 📂vendor
 ┃ ┣ 📜.env
 ┃ ┣ 📜.env.example
 ┃ ┣ 📜.env.local
 ┃ ┣ 📜.env.prod
 ┃ ┣ 📜.env.staging
 ┃ ┣ 📜.gitignore
 ┃ ┣ 📜artisan
 ┃ ┣ 📜composer.json
 ┃ ┣ 📜composer.lock
 ┃ ┣ 📜package.json
 ┃ ┣ 📜phpstan.neon
 ┃ ┣ 📜phpunit.xml
 ┃ ┣ 📜Sanctum Toten Auth.postman_collection.json
 ┣ 📜.gitattributes
 ┣ 📜.gitignore
 ┣ 📜docker-compose.local.yml
 ┣ 📜docker-compose.prod.yml
 ┣ 📜docker-compose.staging.yml
 ┣ 📜docker-compose.yml
 ┗ 📜README.md
```

This structure helps maintain a clear separation of concerns, making the project easier to navigate and scale.

-----

## ➡️ API Routes

The API is versioned under the `/v1` prefix.

### 🔓 Public Routes (`/v1/auth`)

These routes are publicly accessible and do not require a token.

| Method | Path | Description |
| :---: | :---: | :---: |
| `POST` | `/v1/auth/register` | Registers a new user account. 📝 |
| `POST` | `/v1/auth/login` | Authenticates a user and returns a Sanctum token. 🔑 |
| `POST` | `/v1/auth/forgot-password` | Initiates the password reset process. ❓ |
| `POST` | `/v1/auth/reset-password` | Resets a user's password using a valid token. 🔄 |
| `POST` | `/v1/auth/resend-verification-email` | Resends the email verification link. 📧 |
| `POST` | `/v1/auth/verify-email/{id}/{hash}` | Verifies a user's email address. ✅ |
| `GET` | `/v1/health` | A simple health check endpoint. ❤️‍🩹 |

### 🔒 Protected Routes (`/v1/auth`)

These routes require a valid Sanctum token and a verified email address (`auth:sanctum` and `verified` middleware).

| Method | Path | Description |
| :---: | :---: | :---: |
| `POST` | `/v1/auth/refresh-token` | Generates a new token for the authenticated user. 🔄 |
| `POST` | `/v1/auth/logout` | Revokes the current API token. 🚪 |

### 🛡️ Role-Based Access Routes

These routes require both a valid token and a specific role.

| Method | Path | Required Role(s) |
| :---: | :---: | :---: |
| `GET` | `/v1/admin` | `Admin` or `Super Admin` 👑 |
| `GET` | `/v1/user` | `User` 🧑‍💻 |
| `GET` | `/v1/subscriber` | `Subscriber` 🔔 |

-----

## 🚀 Getting Started

### ⚙️ Prerequisites

  - Docker and Docker Compose 🐳
  - Git 🐙

### 🛠️ Installation Steps

1.  **Clone the repository:**

    ```bash
    git clone [repository-url]
    cd [project-directory]
    ```

2.  **Set up the environment:**
    Use the appropriate `docker-compose` and `.env` file for your desired environment.

    **For Local (Dev):**

    ```bash
    cp docker-compose.local.yml docker-compose.yml
    cd src && cp .env.local .env
    ```

    **For Staging:**

    ```bash
    cp docker-compose.staging.yml docker-compose.yml
    cd src && cp .env.staging .env
    ```

    **For Production:**

    ```bash
    cp docker-compose.prod.yml docker-compose.yml
    cd src && cp .env.prod .env
    ```

3.  **Build and run the Docker containers:**

    ```bash
    docker-compose up -d --build
    ```
4.  **Run migrations and seed the database:**

    ```bash
    docker-compose exec app php artisan migrate --seed
    ```

The API will now be running and accessible at `http://localhost:8000`. 🎉

-----

## 📬 Postman Collection

A **Postman Collection** is included at:

```
src/Sanctum Toten Auth.postman_collection.json
```

You can import this collection into Postman and test the full authentication flow

-----

## 🧪 Running Tests & Code Quality Checks

To run the full test suite and code quality checks, execute the following commands.

### **PHPUnit**

To run the full test suite and check code coverage, execute the following command:

```bash
docker-compose exec app vendor/bin/phpunit --testdox --coverage-html
```

To generate an HTML report of the code coverage, which will be saved in the `src/coverage-html` directory, use this command:

```bash
docker-compose exec app vendor/bin/phpunit --testdox --coverage-html=coverage-html
```
To check coverage open `coverage-html/index.html` in a browser.

### **PHP-CS-Fixer** 🎨

PHP-CS-Fixer checks and fixes code style to ensure PSR-12 compliance.

  * **Check for code style violations:**
    ```bash
    docker-compose exec app vendor/bin/php-cs-fixer fix app --dry-run --diff --verbose
    ```
  * **Fix all code style violations:**
    ```bash
    docker-compose exec app vendor/bin/php-cs-fixer fix app
    ```

### **Larastan (PHPStan)** 🧐

Larastan performs static analysis to find potential bugs and code smells.

  * **Run a full static analysis:**
    ```bash
    docker-compose exec app vendor/bin/phpstan analyse
    ```
  * **Generate a baseline to ignore existing errors:**
    ```bash
    docker-compose exec app vendor/bin/phpstan analyse --generate-baseline
    ```

-----

## 🤝 Contributing

We welcome contributions\! 🙏 Please ensure your pull requests meet the following criteria:

  - Adhere to **100% PSR-12** standards. ✅
  - Include comprehensive tests to maintain **100% test coverage**. ✅🧪
  - Ensure all **GitHub Actions** workflows pass successfully. 🚦

-----

## 📜 License

This project is open-sourced software licensed under the **MIT license**. 📄