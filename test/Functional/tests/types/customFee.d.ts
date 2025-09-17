type CustomFee = {
    code: string,
    title: string,
    type: 'Fixed' | 'Percent',
    status: 'enabled' | 'disabled',
    base_amount: number,
    amount: number,
    base_refund_amount: number,
    refund_amount: number,
    advanced: {
        conditions?: CustomFeeCondition,
        show_percentage?: boolean,
    },
}

type CustomFeeCondition = {
    type: string,
    value: string,
    aggregator?: 'any' | 'all',
    attribute?: string,
    operator?: string,
    conditions?: CustomFeeCondition[],
}
