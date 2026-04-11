"use client";
import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "../../features/crm/contexts/AuthContext";
import Background from "../../features/crm/components/Background/Background";
import LoginView from "../../features/crm/views/LoginView";

export default function LoginPage() {
  const { sessionStatus, authLoading, login } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (sessionStatus === "authenticated") {
      router.replace("/dashboard");
    }
  }, [sessionStatus, router]);

  return (
    <>
      <Background />
      <LoginView loading={authLoading} onLogin={login} />
    </>
  );
}
