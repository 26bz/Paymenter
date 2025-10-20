<?php

namespace App\Livewire\Client;

use App\Helpers\ExtensionHelper;
use App\Livewire\Component;
use App\Models\Currency;
use App\Models\Gateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class PaymentMethods extends Component
{
    use WithPagination;

    #[Url('setup', except: false, nullable: true)]
    public $setupModalVisible = false;

    #[Url('currency')]
    public $currency;

    public $gateway;

    private $setup = null;

    public $inactive = false;

    public function mount()
    {
        $this->currency = session('currency', config('settings.default_currency'));
    }

    #[Computed]
    private function gateways()
    {
        $gateways = ExtensionHelper::getBillingAgreementGateways($this->currency);
        if (count($gateways) > 0 && !$this->gateway) {
            $this->gateway = $gateways[0]->id;
        }
        return $gateways;
    }

    public function updatedCurrency()
    {
        $this->reset('gateway');
    }

    public function updatedSetupModalVisible($value)
    {
        // If we have only one gateway and currency, we can auto-select them
        if ($value && count($this->gateways) === 1 && Currency::count() === 1) {
            $this->currency = Currency::first()->code;
            $this->gateway = $this->gateways[0]->id;
            $this->createBillingAgreement();
        }
    }

    public function createBillingAgreement()
    {
        $this->validate([
            'currency' => 'required|exists:currencies,code',
            'gateway' => 'required',
        ]);

        // Check if gateway is valid and supports the selected currency
        if (!in_array($this->gateway, array_column($this->gateways, 'id'))) {
            $this->addError('gateway', __('account.invalid_payment_gateway'));
            return;
        }

        $this->setup = ExtensionHelper::createBillingAgreement(Auth::user(), Gateway::find($this->gateway), $this->currency);

        // If setup is a string, it's a URL to redirect to
        if (is_string($this->setup)) {
            $this->redirect($this->setup);
        }
    }

    public function removePaymentMethod($billingAgreementUlid)
    {
        $billingAgreement = Auth::user()->billingAgreements()->where('ulid', $billingAgreementUlid)->first();
        if (!$billingAgreement) {
            return $this->notify('Billing agreement not found', 'error');
        }

        // Call the gateway to cancel the billing agreement if supported
        $succeeded = ExtensionHelper::cancelBillingAgreement($billingAgreement);

        if ($succeeded) {
            $this->notify('Payment method removed successfully', 'success');
            $billingAgreement->delete();
        } else {
            $this->notify('Failed to remove payment method', 'error');
        }
    }

    public function cancelSetup()
    {
        $this->setupModalVisible = false;
    }

    public function render()
    {
        return view('client.account.payment-methods', [
            'transactions' => Auth::user()->transactions()->with(['invoice', 'gateway'])->latest()->paginate(config('settings.pagination')),
            'billingAgreements' => Auth::user()->billingAgreements()->latest()->get(),
        ])->layoutData([
                    'sidebar' => true,
                    'title' => 'Payment Methods',
                ]);
    }
}
