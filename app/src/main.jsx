import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App.jsx';
import { AuthProvider } from './controllers/AuthContext.jsx';
import 'leaflet/dist/leaflet.css';
import './styles/global.css';
import './styles/layout.css';
import './styles/landing.css';
import './styles/auth.css';
import './styles/explore.css';
import './styles/reservations.css';

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <App />
      </AuthProvider>
    </BrowserRouter>
  </React.StrictMode>
);
