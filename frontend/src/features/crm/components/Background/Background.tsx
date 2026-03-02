"use client";
import React from "react";
import styles from "./Background.module.css";

const Background: React.FC = () => (
  <>
    <div className={styles.base} />
    <div className={styles.gradient} />
    <div className={styles.grid} />
  </>
);

export default Background;
