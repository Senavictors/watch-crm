"use client";

import React, { FormEvent, useState } from "react";
import { Btn, Input } from "../ui/Primitives";
import styles from "./LoginView.module.css";

type Props = {
  loading: boolean;
  onLogin: (email: string, password: string) => Promise<void>;
};

const LoginView: React.FC<Props> = ({ loading, onLogin }) => {
  const [email, setEmail] = useState("admin@watchcrm.local");
  const [password, setPassword] = useState("Admin123456!");
  const [error, setError] = useState("");

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");

    if (!email.trim() || !password.trim()) {
      setError("Preencha e-mail e senha para entrar.");
      return;
    }

    try {
      await onLogin(email.trim(), password);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Não foi possível entrar.");
    }
  }

  return (
    <div className={styles.shell}>
      <div className={styles.panel}>
        <div className={styles.overline}>CRM interno</div>
        <h1 className={styles.title}>Acesse o Watch CRM</h1>
        <p className={styles.subtitle}>
          Sessão segura com permissões por papel e auditoria de ações críticas.
        </p>

        <form className={styles.form} onSubmit={handleSubmit}>
          <Input label="E-mail" value={email} onChange={(event) => setEmail(event.target.value)} />
          <Input
            label="Senha"
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
          />

          {error ? <div className={styles.error}>{error}</div> : null}

          <Btn className={styles.submit} variant="primary">
            {loading ? "Entrando..." : "Entrar"}
          </Btn>
        </form>

        <div className={styles.hint}>
          Usuários seed:
          <br />
          admin@watchcrm.local
          <br />
          gerente@watchcrm.local
          <br />
          vendedor@watchcrm.local
        </div>
      </div>
    </div>
  );
};

export default LoginView;
