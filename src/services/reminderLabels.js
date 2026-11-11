// Match the keys, icon names and color roles used by payment rows.
const paymentLabels = {
 important: ['important', 'warning', 'important'],
 review: ['review', 'error', 'review'],
 fixedCost: ['fixed', 'neutral', 'fixedCosts'],
 childRelated: ['child', 'neutral', 'children'],
 subscription: ['abo', 'neutral', 'subscription'],
 taxRelevant: ['tax', 'tax', 'taxRelevant'],
 insurance: ['insurance', 'neutral', 'insurance'],
}
export default function paymentLabelBadges(ids, texts) {
 return [...new Set(ids || [])].filter(id => paymentLabels[id]).map(id => {
  const [key, tone, text] = paymentLabels[id]
  return { key, icon: id, tone, text: texts.labels[text]() }
 })
}
