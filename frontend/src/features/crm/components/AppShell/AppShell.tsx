"use client";
import React, { useState } from "react";
import styles from "./AppShell.module.css";

type Props = {
  sidebar: React.ReactNode;
  children: React.ReactNode;
};

const AppShell: React.FC<Props> = ({ sidebar, children }) => {
  const [collapsed, setCollapsed] = useState(false);

  const sidebarWithProps = React.isValidElement(sidebar)
    ? React.cloneElement(sidebar as React.ReactElement<{ collapsed?: boolean; onToggle?: () => void }>, {
        collapsed,
        onToggle: () => setCollapsed((v) => !v),
      })
    : sidebar;

  return (
    <div className={styles.root} data-collapsed={collapsed ? "true" : "false"}>
      <div className={styles.sidebar}>{sidebarWithProps}</div>
      <div className={styles.content}>{children}</div>
    </div>
  );
};

export default AppShell;
