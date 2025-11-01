# Trackfolio Frontend

Vue 3 SPA frontend application for Trackfolio.

## Technology Stack

- **Vue 3** (latest stable version) with TypeScript
- **Vue Router** for routing
- **Pinia** for state management
- **Axios** for HTTP client
- **Tailwind CSS** with **DaisyUI** for styling

## Project Structure

```
trackfolio-front/
├── src/
│   ├── api/
│   │   └── axios.ts          # Axios client configured with base URL and credentials
│   ├── assets/
│   │   └── main.css          # Tailwind CSS imports
│   ├── router/
│   │   └── index.ts          # Vue Router configuration
│   ├── views/
│   │   └── Home.vue          # Home page component (Hello World)
│   ├── App.vue               # Root component
│   ├── main.ts               # Application entry point
│   ├── shims-vue.d.ts        # TypeScript declarations for .vue files
│   └── vite-env.d.ts         # Environment variable type definitions
├── package.json              # Project dependencies and scripts
├── tsconfig.json             # TypeScript configuration
├── vite.config.ts            # Vite configuration with API proxy
├── tailwind.config.js        # Tailwind CSS and DaisyUI configuration
└── README.md                 # Project documentation
```

### Key Features Configured

- **Development Proxy**: `/api` and `/sanctum` routes are proxied to `http://localhost:8080`
- **Axios Configuration**: Cookies enabled for Sanctum CSRF protection
- **Environment Variables**: `VITE_API_BASE_URL` configured for API connection
- **TypeScript**: Full type support configured

## Setup

1. Install dependencies:
```bash
npm install
```

2. Create a `.env` file from `.env.example` and configure the API base URL:
```bash
cp .env.example .env
```

3. Start the development server:
```bash
npm run dev
```

The application will be available at `http://localhost:3000`.

## Development

- Development server: `npm run dev`
- Build for production: `npm run build`
- Preview production build: `npm run preview`

## API Configuration

The frontend is configured to communicate with the Trackfolio API. Make sure the API is running on the configured URL (default: `http://localhost:8080`).

Axios is configured to:
- Send credentials (cookies) for CSRF protection
- Use the base URL from environment variables
- Handle JSON requests/responses

