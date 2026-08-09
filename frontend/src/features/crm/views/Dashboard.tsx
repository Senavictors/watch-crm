"use client";
import React from "react";
import { fmtBRL, fmtDate } from "../helpers";
import { AiSummaryResponse, DashboardSummaryResponse } from "../types";
import { Btn, Card } from "../ui/Primitives";
import CategoryDonut from "./dashboard/CategoryDonut";
import AiSummaryCard from "./dashboard/AiSummaryCard";
import ChannelBars from "./dashboard/ChannelBars";
import EvolutionChart from "./dashboard/EvolutionChart";
import GoalProgress from "./dashboard/GoalProgress";
import KpiCard from "./dashboard/KpiCard";
import NextShipmentsList from "./dashboard/NextShipmentsList";
import OperationalAlerts from "./dashboard/OperationalAlerts";
import PeriodSelector from "./dashboard/PeriodSelector";
import { PeriodPresetId } from "./dashboard/periodPresets";
import styles from "./Dashboard.module.css";

type Props = {
  summary: DashboardSummaryResponse | null;
  refreshing: boolean;
  error: string | null;
  preset: PeriodPresetId;
  customFrom: string;
  customTo: string;
  onPresetChange: (preset: PeriodPresetId) => void;
  onCustomFromChange: (value: string) => void;
  onCustomToChange: (value: string) => void;
  onRefresh: () => void;
  onCategoryClick: (category: string) => void;
  aiSummary?: AiSummaryResponse | null;
  aiLoading?: boolean;
  aiError?: string | null;
  onGenerateAi?: () => void;
};

const Dashboard: React.FC<Props> = ({
  summary,
  refreshing,
  error,
  preset,
  customFrom,
  customTo,
  onPresetChange,
  onCustomFromChange,
  onCustomToChange,
  onRefresh,
  onCategoryClick,
  aiSummary,
  aiLoading = false,
  aiError = null,
  onGenerateAi,
}) => {
  return (
    <div>
      <h2 className={styles.title}>Dashboard</h2>

      <PeriodSelector
        preset={preset}
        customFrom={customFrom}
        customTo={customTo}
        refreshing={refreshing}
        onPresetChange={onPresetChange}
        onCustomFromChange={onCustomFromChange}
        onCustomToChange={onCustomToChange}
        onRefresh={onRefresh}
      />

      {error && !summary && (
        <Card className={styles.errorCard}>
          <div className={styles.errorMessage}>{error}</div>
          <Btn onClick={onRefresh} variant="primary">Tentar novamente</Btn>
        </Card>
      )}

      {!summary && !error && (
        <div className={styles.placeholder}>Carregando...</div>
      )}

      {summary && (
        <>
          <div className={styles.periodCaption}>
            {fmtDate(summary.period.from)} — {fmtDate(summary.period.to)}
            <span className={styles.periodCaptionMuted}>
              {" "}· comparado com {fmtDate(summary.comparison.from)} — {fmtDate(summary.comparison.to)}
            </span>
          </div>

          <OperationalAlerts alerts={summary.operationalAlerts} />

          {onGenerateAi && (
            <AiSummaryCard
              summary={aiSummary ?? null}
              loading={aiLoading}
              error={aiError}
              onGenerate={onGenerateAi}
            />
          )}

          {error && (
            <Card className={styles.errorBanner}>
              <span>{error}</span>
              <Btn onClick={onRefresh} variant="secondary" small>Tentar novamente</Btn>
            </Card>
          )}

          <div className={styles.statsRow}>
            {summary.kpis.revenue && (
              <KpiCard
                label="Faturamento"
                value={fmtBRL(summary.kpis.revenue.value)}
                percentageChange={summary.kpis.revenue.percentageChange}
                accent="var(--crm-accent)"
              />
            )}
            {summary.kpis.revenue && (
              <KpiCard
                label="Ticket Médio"
                value={
                  summary.kpis.ordersCount.value > 0
                    ? fmtBRL(summary.kpis.revenue.value / summary.kpis.ordersCount.value)
                    : "—"
                }
                accent="var(--crm-primary)"
              />
            )}
            {summary.kpis.salesProfit && (
              <KpiCard
                label="Lucro das Vendas"
                value={fmtBRL(summary.kpis.salesProfit.value)}
                percentageChange={summary.kpis.salesProfit.percentageChange}
                accent="var(--crm-primary)"
              />
            )}
            {summary.kpis.netResult && (
              <KpiCard
                label="Resultado Líquido"
                value={fmtBRL(summary.kpis.netResult.value)}
                percentageChange={summary.kpis.netResult.percentageChange}
                accent={summary.kpis.netResult.value >= 0 ? "var(--crm-success)" : "var(--crm-danger)"}
              />
            )}
            <KpiCard
              label="Relógios Vendidos"
              value={String(summary.kpis.watchesSold.value)}
              sub={`${summary.kpis.ordersCount.value} pedidos`}
              percentageChange={summary.kpis.watchesSold.percentageChange}
            />
            <KpiCard
              label="Conversão de Pagamento"
              value={summary.kpis.conversionRate.value === null ? "—" : `${summary.kpis.conversionRate.value.toFixed(1)}%`}
              sub={`${summary.conversion.current.paidOrders} de ${summary.conversion.current.ordersCreated} pedidos`}
              percentageChange={summary.kpis.conversionRate.percentagePointChange}
              changeSuffix="p.p."
              accent={
                summary.kpis.conversionRate.percentagePointChange !== null
                  && summary.kpis.conversionRate.percentagePointChange < 0
                  ? "var(--crm-danger)"
                  : "var(--crm-primary)"
              }
            />
            <KpiCard
              label="Pedidos Ativos"
              value={String(summary.kpis.activeOrders.value)}
            />
            <KpiCard
              label="Aguardando Pagamento"
              value={fmtBRL(summary.kpis.pendingAmount.value)}
              sub={`${summary.pendingPayments.count} pedido(s) · média ${summary.pendingPayments.averageWaitHours.toFixed(1)}h`}
            />
            {summary.kpis.generalExpenses && (
              <KpiCard
                label="Despesas Gerais"
                value={fmtBRL(summary.kpis.generalExpenses.value)}
                percentageChange={summary.kpis.generalExpenses.percentageChange}
                accent="var(--crm-danger)"
              />
            )}
            {summary.commission && (
              <KpiCard
                label="Comissões"
                value={fmtBRL(summary.commission.accrued)}
                sub={`Pago: ${fmtBRL(summary.commission.paid)} · Pendente: ${fmtBRL(summary.commission.pending)}`}
              />
            )}
            {summary.stock && (
              <KpiCard
                label="Estoque Atual"
                value={fmtBRL(summary.stock.totalCost)}
                sub={`Potencial: ${fmtBRL(summary.stock.totalPotentialRevenue)}`}
              />
            )}
          </div>

          <div className={styles.gridTwo}>
            <EvolutionChart
              buckets={summary.evolution}
              grouping={summary.period.grouping}
              period={summary.period}
              comparison={summary.comparison}
              preset={preset}
              refreshing={refreshing}
              onPresetChange={onPresetChange}
              totals={{
                revenue: summary.kpis.revenue,
                salesProfit: summary.kpis.salesProfit,
                watchesSold: summary.kpis.watchesSold,
                ordersCount: summary.kpis.ordersCount,
              }}
            />
            <GoalProgress company={summary.goal.company} individual={summary.goal.individual} />
          </div>

          <div className={styles.gridTwo}>
            {summary.categories && (
              <CategoryDonut categories={summary.categories} onCategoryClick={onCategoryClick} />
            )}
            {summary.channels && <ChannelBars channels={summary.channels} />}
          </div>

          <NextShipmentsList shipments={summary.nextShipments} />
        </>
      )}
    </div>
  );
};

export default Dashboard;
