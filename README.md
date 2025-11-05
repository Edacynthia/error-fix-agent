# ErrorFixer AI Agent - Telex.im Integration

![ErrorFixer Banner](https://via.placeholder.com/800x200/6366f1/ffffff?text=ErrorFixer+AI+Agent)

## 🚀 Overview

**ErrorFixer** is an intelligent AI agent integrated with Telex.im that analyzes code errors, detects programming languages, explains root causes, and provides accurate fixes. Built with Laravel and powered by Google Gemini AI.

## ✨ Features

- 🔍 **Automatic Language Detection** - Identifies PHP, JavaScript, Python, and more
- 🐛 **Error Classification** - Categorizes syntax, runtime, and logic errors
- 💡 **Root Cause Analysis** - Explains what went wrong and why
- 🔧 **Smart Fixes** - Provides corrected code snippets
- 📱 **Dual Interface** - Works on Telex.im chat and REST API
- ⚡ **Fast Response** - Powered by Gemini 2.5 Flash


## 📋 Prerequisites

- PHP 8.1 or higher
- Laravel 10.x or higher
- Composer
- Google Gemini API Key
- Telex.im account

## 🔧 Installation

### 1. Clone the Repository
```bash
git clone https://github.com/yourusername/error-fixer-agent.git
cd error-fixer-agent
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure Environment Variables

Edit `.env` and add:
```env
GEMINI_API_KEY=your_gemini_api_key_here
APP_URL=https://your-app-url.com
```

Get your Gemini API key from: https://makersuite.google.com/app/apikey

### 5. Set Up Laravel Configuration

Create or update `config/laragent.php`:
```php
<?php

return [
    'providers' => [
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
        ],
    ],
];
```

### 6. Deploy to Railway (or your preferred host)
```bash
# Install Railway CLI
npm install -g @railway/cli

# Login
railway login

# Deploy
railway up
```

Your endpoint will be: `https://your-app.up.railway.app/api/error-fix`

## 🔌 Telex.im Integration

### Step 1: Create Workflow JSON

Create a file named `workflow.json`:
```json
{
  "active": true,
  "category": "utilities",
  "description": "Detect, explain, and fix syntax or runtime code errors safely.",
  "id": "laravelErrorFixAgent01",
  "long_description": "You are a professional debugging and code repair assistant.\n\n### ROLE:\nYou analyze code and error messages to:\n1. Detect what programming language the code or error belongs to (PHP, JavaScript, Python, etc.).\n2. Identify the error type (SyntaxError, RuntimeError, Missing Variable, etc.).\n3. Explain clearly what caused the issue.\n4. Suggest a safe, corrected version of the code.\n5. Never execute or simulate the code — only analyze logically.\n6. If the code is unsafe (e.g., includes system commands, file access, eval, etc.), respond with: { \"error\": \"Input contains unsafe or malicious content.\" }\n\n### RESPONSE RULES:\n- Always return clean, valid JSON in this format:\n  {\n    \"language\": \"Detected programming language name\",\n    \"error_type\": \"Type of error (syntax, runtime, logic, etc.)\",\n    \"cause\": \"Explanation of what caused the issue.\",\n    \"fix\": \"Proposed corrected code snippet or safe adjustment.\",\n    \"notes\": \"Advice on how to avoid the issue in the future.\"\n  }\n- Never add introductions or Markdown formatting.\n- Never output text outside the JSON structure.",
  "name": "Error Fix Agent",
  "nodes": [
    {
      "id": "debug-code-node",
      "name": "Error Fix Agent",
      "parameters": {
        "error": "string (REQUIRED - raw code or error message to analyze)"
      },
      "position": [500, 150],
      "type": "a2a/mastra-a2a-node",
      "typeVersion": 1,
      "url": "https://error-fix-agent-production.up.railway.app/error-fix"
    }
  ],
  "settings": {
    "executionOrder": "v1"
  },
  "short_description": "Analyze and fix raw code or error messages safely. Always return valid JSON with language, error type, cause, fix, and notes."
}
```

### Step 2: Upload to Telex.im

1. Go to https://telex.im
2. Navigate to your workspace
3. Go to Home → AI cowoker → Add new co-Worker
4. click on profile and click on configure workflow
4. Upload your `workflow.json`
5. Activate the agent

### Step 3: Test the Integration

Send messages to your agent on Telex:

- **"hi"** → Get welcome message
- **"PHP Fatal error: Call to undefined method"** → Get error analysis

## 📡 API Documentation

### Endpoint
```
POST https://error-fix-agent-production.up.railway.app/error-fix

### Request Format (Telex.im)
```json
{
  "event": {
    "message": {
      "text": "your error message or greeting"
    }
  }
}
```

### Request Format (Postman/API)
```json
{
  "error": "PHP Fatal error: Call to undefined method User::fullname()"
}
```

Or:
```json
{
  "text": "JavaScript TypeError: Cannot read property of undefined"
}
```

### Response Format (Telex.im)
```json
{
  "text": "🧠 *Code Analysis*\n\n🔹 Language: PHP\n🔹 Error Type: Fatal Error\n\n💡 Cause:\nThe method fullname() does not exist...\n\n🔧 Fix:\n...\n\n📌 Notes:\n..."
}
```

### Response Format (Postman/API)
```json
{
  "language": "PHP",
  "error_type": "Fatal Error",
  "cause": "The method fullname() does not exist in the User class.",
  "fix": "Define the method: public function fullname() { return $this->first_name . ' ' . $this->last_name; }",
  "notes": "Always ensure methods are defined before calling them."
}
```

## 🧪 Testing

### Test with Postman

**Greeting Test:**
```bash
POST https://error-fix-agent-production.up.railway.app/error-fix
Content-Type: application/json

{
  "text": "hi"
}
```

**Error Analysis Test:**
```bash
POST https://error-fix-agent-production.up.railway.app/error-fix
Content-Type: application/json

{
  "error": "PHP Parse error: syntax error, unexpected '}' in /app.php on line 42"
}
```

### Test on Telex.im

1. Open your Telex workspace
2. Find your ErrorFixer agent
3. Send: `hi`
4. Send: `PHP Fatal error: Call to undefined function mysqli_connect()`

## 👨‍💻 Author

**Eda Cynthia Itsekirimi**
- Email: edacynthia3@gmail.com
- github: https://github.com/Edacynthia/error-fix-agent

## 🔗 Links

- **Live Demo:** https://error-fix-agent-production.up.railway.app/error-fix

