import { Navigate, Route, Routes } from "react-router-dom";
import { DashboardPage } from "@/pages/dashboard";
import { LoginPage } from "@/pages/login";
import { PaymentsPage } from "@/pages/payments";
import { AdminLayout } from "@/layouts/admin-layout";
import { CheckoutPage } from "@/pages/checkout";
import { ChangePasswordPage } from "@/pages/change-password";
import { TopUpPage } from "@/pages/top-up";
import { PaymentOrdersPage } from "@/pages/payment-orders";
import { SoftwarePage } from "@/pages/software";
import { LicensesPage } from "@/pages/licenses";
import { UsersPage } from "@/pages/users";
import { RegisterPage } from "@/pages/register";
import { AccountPage } from "@/pages/account";
import { QuotasPage } from "@/pages/quotas";
import { SettingsPage } from "@/pages/settings";

export default function App() {
  return (
    <Routes>
      <Route path="/checkout/:orderNo/:token" element={<CheckoutPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/change-password" element={<ChangePasswordPage />} />
      <Route element={<AdminLayout />}>
        <Route path="/" element={<DashboardPage />} />
        <Route path="/apps" element={<SoftwarePage />} />
        <Route path="/licenses" element={<LicensesPage />} />
        <Route path="/quotas" element={<QuotasPage />} />
        <Route path="/users" element={<UsersPage />} />
        <Route path="/account" element={<AccountPage />} />
        <Route path="/payments" element={<PaymentsPage />} />
        <Route path="/top-up" element={<TopUpPage />} />
        <Route path="/payment-orders" element={<PaymentOrdersPage />} />
        <Route path="/settings" element={<SettingsPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}