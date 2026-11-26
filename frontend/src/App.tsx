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
import { SettingsPage } from "@/pages/settings";
import { QuotasPage } from "@/pages/quotas";
import { AgentLicensesPage } from "@/pages/agent-licenses";
import { AgentQuotasPage } from "@/pages/agent-quotas";
import { SecurityKeysPage } from "@/pages/security-keys";
import { AuditLogsPage } from "@/pages/audit-logs";
import { DeveloperCenterPage } from "@/pages/developer-center";
import { MonitoringPage } from "@/pages/monitoring";
import { RegionsPage } from "@/pages/regions";
import { ApiKeysPage } from "@/pages/api-keys";
import { NotificationsPage } from "@/pages/notifications";
import { WalletManagementPage } from "@/pages/wallet-management";
import { WalletPage } from "@/pages/wallet";

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
        <Route path="/agent/licenses" element={<AgentLicensesPage />} />
        <Route path="/quotas" element={<QuotasPage />} />
        <Route path="/agent/quotas" element={<AgentQuotasPage />} />
        <Route path="/users" element={<UsersPage />} />
        <Route path="/account" element={<AccountPage />} />
        <Route path="/notifications" element={<NotificationsPage />} />
        <Route path="/payments" element={<PaymentsPage />} />
        <Route path="/wallet" element={<WalletPage />} />
        <Route path="/top-up" element={<TopUpPage />} />
        <Route path="/wallet-management" element={<WalletManagementPage />} />
        <Route path="/payment-orders" element={<PaymentOrdersPage />} />
        <Route path="/security-keys" element={<SecurityKeysPage />} />
        <Route path="/api-keys" element={<ApiKeysPage />} />
        <Route path="/audit-logs" element={<AuditLogsPage />} />
        <Route path="/developer-center" element={<DeveloperCenterPage />} />
        <Route path="/monitoring" element={<MonitoringPage />} />
        <Route path="/regions" element={<RegionsPage />} />
        <Route path="/settings" element={<SettingsPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
