"use client";
import React from "react";
import styles from "./AppShell.module.css";

type Props = {
  sidebar: React.ReactNode;
  children: React.ReactNode;
};

const AppShell: React.FC<Props> = ({ sidebar, children }) => (
  <div className={styles.root}>
    <div className={styles.sidebar}>{sidebar}</div>
    <div className={styles.content}>{children}</div>
  </div>
);

export default AppShell;
