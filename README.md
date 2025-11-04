# ErrorFixer Agent

ErrorFic=xer Agent is an AI-powered debugging assistant built with Laravel. It analyzes raw code or error messages, detects the language, identifies the type of error, explains the cause, and provides a safe, corrected fix with guidance.

## Features
- Detect programming language from error text.
- Identify error type (syntax, runtime, logic).
- Explain the cause in plain English.
- Return safe and structured fix instructions.
- Always responds in strict JSON format.

## Tech Stack
- **Backend:** PHP/Laravel
- **AI Model:** Gemini-2.5-flash
- **Integration:** Telex A2A Webhook

## Public Webhook Endpoint
```
https://error-fix-agent-production.up.railway.app/error-fix
```

## How It Works
1. Paste any error or broken code.
2. The agent analyzes the message.
3. It responds in JSON with language, cause, fix, and notes.

## Example
**Input:**
```
ModuleNotFoundError: No module named 'requests'
```

**Response:**
```json
{
  "language": "Python",
  "error_type": "Runtime Error (ModuleNotFoundError)",
  "cause": "The 'requests' module is not installed in the Python environment where the code is being executed, or the active environment does not have it accessible.",
  "fix": "Open your terminal or command prompt and run: `pip install requests`",
  "notes": "Always ensure all necessary third-party libraries are installed in your Python environment. For project-specific dependencies, consider using virtual environments (`venv` or `conda`) and a `requirements.txt` file to manage and install them (`pip install -r requirements.txt`)."
}
```

## Running Locally

```bash
git clone <your-repo-url>
cd project
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

Ensure the `.env` file contains your Gemini key


## Deployment
This project is deployed on **Railway.app**.
