<?php

namespace App\Http\Livewire\Admin\Masters;

use App\Models\Admin\PaymentGateway;
use Illuminate\Validation\Rule;

class PaymentGatewaysManager extends MasterCrudComponent
{
    protected function modelClass(): string
    {
        return PaymentGateway::class;
    }

    protected function module(): string
    {
        return 'payment_gateways';
    }

    protected function title(): string
    {
        return 'Payment Gateways';
    }

    protected function singular(): string
    {
        return 'Payment Gateway';
    }

    protected function icon(): string
    {
        return 'ti ti-credit-card';
    }

    protected function group(): string
    {
        return 'Finance';
    }

    protected function fields(): array
    {
        return [
            'name' => [
                'label' => 'Gateway Name',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:255', Rule::unique('payment_gateways', 'name')->ignore($this->recordId)],
                'placeholder' => 'e.g. GPay (UPI)',
            ],
            'code' => [
                'label' => 'Code',
                'type' => 'text',
                'rules' => ['required', 'alpha_dash', 'max:50', Rule::unique('payment_gateways', 'code')->ignore($this->recordId)],
                'placeholder' => 'e.g. gpay',
                'help' => 'Used by the system to pick the payment driver. Lowercase, no spaces.',
            ],
            'type' => [
                'label' => 'Type',
                'type' => 'select',
                'rules' => ['required', Rule::in(array_keys(config('camp.payment_gateway_types')))],
                'options' => fn () => config('camp.payment_gateway_types'),
            ],
            'sort_order' => [
                'label' => 'Display Order',
                'type' => 'number',
                'rules' => ['required', 'integer', 'min:0', 'max:999'],
                'placeholder' => '0',
            ],
        ];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Gateway', 'field' => 'name', 'sortable' => true],
            ['label' => 'Code', 'field' => 'code', 'sortable' => true],
            ['label' => 'Type', 'field' => 'type_label', 'sortable' => false],
            ['label' => 'Order', 'field' => 'sort_order', 'sortable' => true],
        ];
    }
}
