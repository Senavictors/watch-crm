"use client";
import React from "react";
import { CrmUser, UserRole } from "../types";
import { Btn } from "../ui/Primitives";
import styles from "./Users.module.css";

type Props = {
  users: CrmUser[];
  search: string;
  role: string;
  status: string;
  onSearchChange: (value: string) => void;
  onRoleChange: (value: string) => void;
  onStatusChange: (value: string) => void;
  currentUserRole: UserRole;
  canCreate: boolean;
  onNew: () => void;
  onEdit: (user: CrmUser) => void;
  onToggleActive: (user: CrmUser) => void;
  onResetPassword: (user: CrmUser) => void;
};

const ROLE_LABELS: Record<string, string> = {
  owner: "Proprietário",
  admin: "Admin",
  gerente: "Gerente",
  vendedor: "Vendedor",
  garantia: "Garantia",
};

const Users: React.FC<Props> = ({
  users,
  search,
  role,
  status,
  onSearchChange,
  onRoleChange,
  onStatusChange,
  currentUserRole,
  canCreate,
  onNew,
  onEdit,
  onToggleActive,
  onResetPassword,
}) => {
  function canActOnUser(target: CrmUser): boolean {
    // TASK-013: gerente também não age sobre owner (mesma regra do backend,
    // UserPolicy::update) — mostrar o botão aqui só pra dar 403 depois seria
    // UX ruim, não controle de acesso (o bloqueio real já é no backend).
    if (currentUserRole === "gerente" && (target.role === "admin" || target.role === "owner")) return false;
    return true;
  }

  function formatLastLogin(val?: string | null): string {
    if (!val) return "Nunca";
    try {
      return new Date(val).toLocaleDateString("pt-BR");
    } catch {
      return "—";
    }
  }

  return (
    <div>
      <div className={styles.headerRow}>
        <h2 className={styles.title}>Usuários</h2>
        {canCreate && (
          <Btn onClick={onNew} variant="primary" className={styles.actionButton}>
            + Adicionar Usuário
          </Btn>
        )}
      </div>

      <div className={styles.filterBar}>
        <input
          value={search}
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder="Buscar por nome ou e-mail..."
          className={styles.search}
        />
        <select
          value={role}
          onChange={(e) => onRoleChange(e.target.value)}
          className={styles.filterSelect}
        >
          <option value="">Todas as funções</option>
          <option value="owner">Proprietário</option>
          <option value="admin">Admin</option>
          <option value="gerente">Gerente</option>
          <option value="vendedor">Vendedor</option>
          <option value="garantia">Garantia</option>
        </select>
        <select
          value={status}
          onChange={(e) => onStatusChange(e.target.value)}
          className={styles.filterSelect}
        >
          <option value="">Todos os status</option>
          <option value="active">Ativos</option>
          <option value="inactive">Bloqueados</option>
        </select>
      </div>

      <div className={styles.tableWrapper}>
        <table className={styles.table}>
          <thead>
            <tr>
              <th className={styles.th}>Nome</th>
              <th className={styles.th}>E-mail</th>
              <th className={styles.th}>Função</th>
              <th className={styles.th}>Status</th>
              <th className={styles.th}>Último Acesso</th>
              <th className={styles.th}>Ações</th>
            </tr>
          </thead>
          <tbody>
            {users.length === 0 && (
              <tr>
                <td colSpan={6} className={styles.empty}>
                  Nenhum usuário encontrado.
                </td>
              </tr>
            )}
            {users.map((u) => (
              <tr key={u.id} className={styles.row}>
                <td className={styles.td}>{u.name}</td>
                <td className={styles.td}>{u.email}</td>
                <td className={styles.td}>
                  <span className={styles.roleChip}>{ROLE_LABELS[u.role] ?? u.role}</span>
                </td>
                <td className={styles.td}>
                  {u.isActive ? (
                    <span className={styles.statusActive}>Ativo</span>
                  ) : (
                    <span className={styles.statusInactive}>Bloqueado</span>
                  )}
                </td>
                <td className={styles.td}>{formatLastLogin(u.lastLoginAt)}</td>
                <td className={`${styles.td} ${styles.actionsCell}`}>
                  {canActOnUser(u) ? (
                    <>
                      <Btn onClick={() => onEdit(u)} variant="secondary" small>
                        Editar
                      </Btn>
                      <Btn
                        onClick={() => onToggleActive(u)}
                        variant={u.isActive ? "danger" : "success"}
                        small
                      >
                        {u.isActive ? "Bloquear" : "Ativar"}
                      </Btn>
                      <Btn onClick={() => onResetPassword(u)} variant="secondary" small>
                        Senha
                      </Btn>
                    </>
                  ) : (
                    <span className={styles.noActions}>—</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};

export default Users;
