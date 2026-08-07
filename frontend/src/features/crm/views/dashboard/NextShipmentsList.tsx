"use client";
import React from "react";
import { fmtDate } from "../../helpers";
import { DashboardNextShipment } from "../../types";
import { Badge, Card } from "../../ui/Primitives";
import styles from "./NextShipmentsList.module.css";

type Props = {
  shipments: DashboardNextShipment[];
};

const NextShipmentsList: React.FC<Props> = ({ shipments }) => {
  return (
    <Card>
      <div className={styles.title}>Próximos Envios</div>
      {shipments.length === 0 ? (
        <div className={styles.empty}>Nenhum envio previsto.</div>
      ) : (
        <div className={styles.list}>
          {shipments.map((shipment) => (
            <div key={shipment.orderId} className={styles.row}>
              <div className={styles.rowMain}>
                <span className={styles.orderId}>#{shipment.orderId}</span>
                <span className={styles.customerName}>{shipment.customerName ?? "—"}</span>
              </div>
              <div className={styles.rowMeta}>
                <Badge status={shipment.status} />
                <span className={styles.shippingMethod}>{shipment.shippingMethod || "—"}</span>
                <span className={styles.saleDate}>{fmtDate(shipment.saleDate)}</span>
              </div>
            </div>
          ))}
        </div>
      )}
    </Card>
  );
};

export default NextShipmentsList;
