export const PAYMENT_PARTNER_DETAIL_FIELDS = [
	['number', 'number'],
	['salutation', 'salutation'],
	['title', 'title'],
	['companyName', 'company_name'],
	['additional', 'additional'],
	['vatId', 'vat_id'],
	['firstName', 'first_name'],
	['lastName', 'last_name'],
	['street', 'street'],
	['postalCode', 'postal_code'],
	['city', 'city'],
	['country', 'country'],
	['addressNote', 'address_note'],
	['email', 'email'],
	['phone', 'phone'],
	['mobile', 'mobile'],
	['fax', 'fax'],
	['web', 'web'],
	['accountHolder', 'account_holder'],
	['iban', 'iban'],
	['bic', 'bic'],
	['bank', 'bank'],
	['bankCode', 'bank_code'],
	['accountNumber', 'account_number'],
	['note', 'note'],
]

const textValue = value => value === null || value === undefined ? '' : String(value)
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/u

export const isValidPaymentPartnerEmail = value => {
	const email = textValue(value).trim()
	return email === '' || (email.length <= 254 && EMAIL_PATTERN.test(email))
}

export const createPaymentPartnerDetails = (source = {}) => Object.fromEntries(
	PAYMENT_PARTNER_DETAIL_FIELDS.map(([field, column]) => [
		field,
		textValue(source?.[column] ?? source?.[field]),
	])
)

export const normalizePaymentPartnerDetails = (details = {}) => Object.fromEntries(
	PAYMENT_PARTNER_DETAIL_FIELDS.map(([field]) => [field, textValue(details?.[field]).trim()])
)

export const paymentPartnerDetailsPayload = details => normalizePaymentPartnerDetails(details)

export const paymentPartnerDetailsChanged = (source, details) => {
	const initial = normalizePaymentPartnerDetails(createPaymentPartnerDetails(source))
	const current = normalizePaymentPartnerDetails(details)

	return PAYMENT_PARTNER_DETAIL_FIELDS.some(([field]) => initial[field] !== current[field])
}
