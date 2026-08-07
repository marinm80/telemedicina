// Tarifas de consulta se muestran en USD en toda la plataforma — referencia
// informal común en Latinoamérica para comparar precios "de bolsillo",
// aunque en EE.UU. una cita médica no se cobra así (seguro/copago).
const USD_FORMATTER = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
  minimumFractionDigits: 0,
  maximumFractionDigits: 2,
});

export function formatUSD(amount: number | null | undefined): string {
  if (!amount) return '—';
  return USD_FORMATTER.format(amount);
}
