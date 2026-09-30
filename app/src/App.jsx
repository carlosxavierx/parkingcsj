import { Routes, Route, useLocation } from 'react-router-dom';
import { useEffect } from 'react';
import Layout from './views/layout/Layout.jsx';
import LandingPage from './views/pages/LandingPage.jsx';
import ExplorePage from './views/pages/ExplorePage.jsx';
import LoginPage from './views/pages/LoginPage.jsx';
import RegisterPage from './views/pages/RegisterPage.jsx';
import MyReservationsPage from './views/pages/MyReservationsPage.jsx';
import NotFoundPage from './views/pages/NotFoundPage.jsx';
import ProtectedRoute from './views/components/ProtectedRoute.jsx';

function ScrollToTop() {
  const { pathname } = useLocation();
  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'instant' });
  }, [pathname]);
  return null;
}

export default function App() {
  return (
    <Layout>
      <ScrollToTop />
      <Routes>
        <Route path="/" element={<LandingPage />} />
        <Route path="/explorar" element={<ExplorePage />} />
        <Route path="/login" element={<LoginPage />} />
        <Route path="/registro" element={<RegisterPage />} />
        <Route
          path="/reservas"
          element={
            <ProtectedRoute>
              <MyReservationsPage />
            </ProtectedRoute>
          }
        />
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
    </Layout>
  );
}
