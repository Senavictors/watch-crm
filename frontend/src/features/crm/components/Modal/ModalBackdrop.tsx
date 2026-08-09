"use client";

import { useEffect, useRef, type MouseEvent, type ReactNode } from "react";
import styles from "./Modal.module.css";

const modalStack: symbol[] = [];

type Props = {
  children: ReactNode;
  onClose: () => void;
};

export default function ModalBackdrop({ children, onClose }: Props) {
  const modalId = useRef(Symbol("modal"));
  const onCloseRef = useRef(onClose);
  onCloseRef.current = onClose;

  useEffect(() => {
    const id = modalId.current;
    modalStack.push(id);

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key !== "Escape" || modalStack.at(-1) !== id) return;
      event.preventDefault();
      onCloseRef.current();
    }

    document.addEventListener("keydown", handleKeyDown);
    return () => {
      document.removeEventListener("keydown", handleKeyDown);
      const index = modalStack.lastIndexOf(id);
      if (index >= 0) modalStack.splice(index, 1);
    };
  }, []);

  function handleBackdropClick(event: MouseEvent<HTMLDivElement>) {
    if (event.target === event.currentTarget) onCloseRef.current();
  }

  return (
    <div className={styles.overlay} onClick={handleBackdropClick} role="presentation">
      {children}
    </div>
  );
}
