import { Navigate, Route, Routes } from "react-router-dom";
import { DashboardPage } from "@/pages/dashboard";
import { LoginPage } from "@/pages/login";
import { PaymentsPage } from "@/pages/payments";
import { AdminLayout } from "@/layouts/admin-layout";
import { CheckoutPage } from "@/pages/checkout";
import { ChangePasswordPage } from "@/pages/change-password";
import { TopUpPage } from "@/pages/top-up";
import { PaymentOrdersPage } from "@/pages/payment-orders";

export default function App() {
  return (
    <Routes>
      <Route path="/checkout/:orderNo/:token" element={<CheckoutPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/change-password" element={<ChangePasswordPage />} />
      <Route element={<AdminLayout />}>
        <Route path="/" element={<DashboardPage />} />
        <Route path="/payments" element={<PaymentsPage />} />
        <Route path="/top-up" element={<TopUpPage />} />
        <Route path="/payment-orders" element={<PaymentOrdersPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}