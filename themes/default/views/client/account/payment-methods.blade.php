<div class="container mt-14">
    <x-navigation.breadcrumb />
    <div class="px-2">
        @if($setupModalVisible)
        <x-modal :title="__('account.payment_methods')" open="true">
            <x-slot name="closeTrigger">
                <div class="flex gap-4">
                    <button wire:click="$set('setupModalVisible', false)" class="text-primary-100">
                        <x-ri-close-fill class="size-6" />
                    </button>
                </div>
            </x-slot>
            @if(count($this->gateways) > 1)
            <x-form.select name="gateway" :label="__('account.input.payment_gateway')" wire:model.live="gateway"
                required>
                @foreach($this->gateways as $gateway)
                <option value="{{ $gateway->id }}">{{ $gateway->name }}</option>
                @endforeach
            </x-form.select>
            @elseif(count($this->gateways) === 0)
            <p class="text-sm text-red-500">{{ __('account.no_payment_gateways_available') }}</p>
            @endif
            <x-button.primary class="w-full mt-4" wire:click="createBillingAgreement" wire:loading.attr="disabled">
                <x-loading target="createBillingAgreement" />
                <div wire:loading.remove wire:target="createBillingAgreement">
                    {{ __('account.setup_payment_method') }}
                </div>
            </x-button.primary>
            @if ($this->setup)
            <x-modal :title="__('account.setup')" open>
                <div class="mt-8">
                    {{ $this->setup }}
                </div>
                <x-slot name="closeTrigger">
                    <div class="flex gap-4">
                        <button wire:confirm="Are you sure?" wire:click="cancelSetup" wire:loading.attr="disabled"
                            wire:target="cancelSetup" class="text-primary-100">
                            <x-ri-close-fill class="size-6" />
                        </button>
                    </div>
                </x-slot>
            </x-modal>
            @endif
        </x-modal>
        @endif
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-2xl font-bold">{{ __('account.saved_payment_methods') }}</h3>
                <p class="text-sm text-base/70">{{ __('account.saved_payment_methods_description') }}</p>
            </div>
            @if(count($this->gateways) > 0)
            <x-button.primary class="h-fit !w-fit" wire:click="$set('setupModalVisible', true)"
                wire:loading.attr="disabled" wire:target="setupModalVisible">
                <x-ri-add-line class="size-4 mr-2" />
                {{ __('account.add_payment_method') }}
            </x-button.primary>
            @endif
        </div>

        @forelse ($billingAgreements as $agreement)
        <div
            class="bg-background-secondary border border-neutral p-4 rounded-lg mb-4 hover:bg-background-secondary/80 transition-colors">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    {{-- Payment method icon --}}
                    <div class="bg-primary/10 p-3 rounded-lg">
                        @if($agreement->gateway->extension === 'Stripe')
                        <x-ri-bank-card-line class="size-6 text-primary" />
                        @elseif($agreement->gateway->extension === 'PayPal')
                        <x-ri-paypal-line class="size-6 text-primary" />
                        @else
                        <x-ri-secure-payment-line class="size-6 text-primary" />
                        @endif
                    </div>

                    {{-- Payment method details --}}
                    <div>
                        <div class="font-semibold text-base">
                            {{ $agreement->name }}
                        </div>
                        <div class="text-sm text-base/70 flex items-center">
                            <p>{{ $agreement->gateway->name }}</p>
                        </div>
                        @if($agreement->services()->count() > 0)
                        <div class="text-xs text-base/50 mt-1">
                            {{ __('account.services_linked', ['count' => $agreement->services()->count()]) }}
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-2">
                    <x-button.danger class="!px-2" x-on:click="$store.confirmation.confirm({
                        title: '{{ __('account.remove_payment_method') }}',
                        message: '{{ __('account.remove_payment_method_confirm', ['name' => $agreement->name]) }}',
                        confirmText: '{{ __('account.confirm') }}',
                        cancelText: '{{ __('account.cancel') }}',
                        callback: () => $wire.removePaymentMethod('{{ $agreement->ulid }}')
                    })">
                        <x-ri-delete-bin-line class="size-4" />
                    </x-button.danger>
                </div>
            </div>
        </div>
        @empty
        <div class="bg-background-secondary border border-neutral p-6 rounded-lg text-center">
            <x-ri-bank-card-line class="size-12 text-base/30 mx-auto mb-3" />
            <p class="text-base/70 mb-4">{{ __('account.no_saved_payment_methods') }}</p>
        </div>
        @endforelse

        <h3 class="text-lg font-bold pb-3 mt-8">{{ __('account.recent_transactions') }}</h3>
        @foreach ($transactions as $transaction)
        <a href="{{ route('invoices.show', $transaction->invoice) }}" wire:navigate>
            <div
                class="bg-background-secondary hover:bg-background-secondary/80 border border-neutral p-1 rounded-lg mb-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-secondary/10 p-2 rounded-lg">
                            <x-ri-bill-line class="size-5 text-secondary" />
                        </div>
                        <span class="font-medium">
                            {{ $transaction->transaction_id ? 'Transaction: ' . $transaction->transaction_id :
                            'Transaction ID N/A' }}
                        </span>
                        <span class="text-base/50 font-semibold">
                            <x-ri-circle-fill class="size-1 text-base/20" />
                        </span>
                        <span class="text-base text-sm">
                            {{ $transaction->formattedAmount }}
                            using
                            {{ $transaction->gateway ? $transaction->gateway->name : 'N/A' }}
                        </span>
                    </div>
                    <div class="pr-2">
                        <!-- Show status -->
                        <span class="text-sm text-base/70 mr-1">
                            @if($transaction->status === \App\Enums\InvoiceTransactionStatus::Succeeded)
                            <span
                                class="inline-flex items-center px-1 py-0.5 pr-1.5 rounded-full text-xs bg-green-100 text-green-800">
                                <x-ri-check-line class="size-3 mr-1" />
                                {{ __('invoices.transaction_statuses.succeeded') }}
                            </span>
                            @elseif($transaction->status === \App\Enums\InvoiceTransactionStatus::Processing)
                            <span
                                class="inline-flex items-center px-1 py-0.5 pr-1.5 rounded-full text-xs bg-yellow-100 text-yellow-800">
                                <x-ri-loader-5-fill class="size-3 mr-1 fill-yellow-600 animate-spin" />
                                {{ __('invoices.transaction_statuses.processing') }}
                            </span>
                            @elseif($transaction->status === \App\Enums\InvoiceTransactionStatus::Failed)
                            <span
                                class="inline-flex items-center px-1 py-0.5 pr-1.5 rounded-full text-xs bg-red-100 text-red-800">
                                <x-ri-close-line class="size-3 mr-1" />
                                {{ __('invoices.transaction_statuses.failed') }}
                            </span>
                            @endif
                        </span>
                        {{ $transaction->created_at->format('d M Y H:i') }}
                    </div>
                </div>
            </div>
        </a>
        @endforeach

        {{ $transactions->links() }}
    </div>
</div>