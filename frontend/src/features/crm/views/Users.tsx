"use client";
import React, { useState } from "react";
import { CrmUser } from "../types";
import { Btn } from "../ui/Primitives";
import styles from "./Users.module.css";

type Props = {
  users: CrmUser[];
  currentUserRole: "admin" | "gerente" | "vendedor";
  canCreate: boolean;
  onNew: () => void;
  onEdit: (user: CrmUser) => void;
  onToggleActive: (user: CrmUser) => void;
  onResetPassword: (user: CrmUser) => void;
};

const ROLE_LABELS: Record<string, string> = {
  admin: "Admin",
  gerente: "Gerente",
  vendedor: "Vendedor",
};

const Users: React.FC<Props> = ({
  users,
  currentUserRole,
  canCreate,
  onNew,
  onEdit,
  onToggleActive,
  onResetPassword,
}) => {
  const [search, setSearch] = useState("");
  const [roleFilter, setRoleFilter] = useState("");
  const [statusFilter, setStatusFilter] = useState("");

  const filtered = users.filter((u) => {
    if (search && !`${u.name} ${u.email}`.toLowerCase().includes(search.toLowerCase())) return false;
    if (roleFilter && u.role !== roleFilter) return false;
    if (statusFilter === "active" && !u.isActive) return false;
    if (statusFilter === "inactive" && u.isActive) return false;
    return true;
  });

  function canActOnUser(target: CrmUser): boolean {
    if (currentUserRole === "gerente" && target.role === "admin") return false;
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
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Buscar por nome ou e-mail..."
          className={styles.search}
        />
        <select
          value={roleFilter}
          onChange={(e) => setRoleFilter(e.target.value)}
          className={styles.filterSelect}
        >
          <option value="">Todas as funções</option>
          <option value="admin">Admin</option>
          <option value="gerente">Gerente</option>
          <option value="vendedor">Vendedor</option>
        </select>
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
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
            {filtered.length === 0 && (
              <tr>
                <td colSpan={6} className={styles.empty}>
                  Nenhum usuário encontrado.
                </td>
              </tr>
            )}
            {filtered.map((u) => (
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
