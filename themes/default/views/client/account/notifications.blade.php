<div>
    <x-navigation.breadcrumb />

    
    <div class="px-2">
        <!-- Configure push notifications -->
        @if($this->supportsPush())
            <div class="bg-background-secondary rounded-lg p-4 mb-6" x-data="pushNotifications">
                <h2 class="text-lg font-medium text-primary mb-2">{{ __('account.push_notifications') }}</h2>
                <p class="text-base/70 mb-4">{{ __('account.push_notifications_description') }}</p>
                <x-button.primary type="button" class="!w-fit" @click="subscribe" x-bind:disabled="subscriptionStatus !== 'not_subscribed'">
                    <x-ri-notification-line class="size-5 mr-2" />
                    {{ __('account.enable_push_notifications') }}
                </x-button.primary>
                <div x-show="subscriptionStatus !== 'unknown'">
                    <template x-if="subscriptionStatus === 'not_supported'">
                        <p class="text-sm text-red-600">{{ __('account.push_status.not_supported') }}</p>
                    </template>
                    <template x-if="subscriptionStatus === 'denied'">
                        <p class="text-sm text-red-600">{{ __('account.push_status.denied') }}</p>
                    </template>
                    <template x-if="subscriptionStatus === 'subscribed'">
                        <p class="text-sm text-green-600">{{ __('account.push_status.subscribed') }}</p>
                    </template>
                </div>
            </div>
            @script
            <script>
                navigator.serviceWorker.register('/service-worker.js').catch(function(error) {
                    console.log('Service Worker registration failed:', error);
                });

                Alpine.data('pushNotifications', () => ({
                    subscriptionStatus: 'unknown',

                    init() {
                        console.log(this.subscriptionStatus)
                        if ('serviceWorker' in navigator && 'PushManager' in window) {
                            navigator.serviceWorker.ready.then((registration) => {
                                registration.pushManager.getSubscription().then((subscription) => {
                                    if (subscription) {
                                        this.subscriptionStatus = 'subscribed';
                                    } else {
                                        this.subscriptionStatus = Notification.permission === 'denied' ? 'denied' : 'not_subscribed';
                                    }
                                });
                            });
                        } else {
                            this.subscriptionStatus = 'not_subscribed';
                        }
                    },

                    subscribe() {
                        if ('serviceWorker' in navigator && 'PushManager' in window) {
                            navigator.serviceWorker.ready.then((registration) => {
                                registration.pushManager.getSubscription().then((subscription) => {
                                    if (subscription) {
                                        @this.call('storePushSubscription', JSON.stringify(subscription));
                                        this.subscriptionStatus = 'subscribed';
                                        return;
                                    }

                                    // Subscribe the user
                                    registration.pushManager.subscribe({
                                        userVisibleOnly: true,
                                        applicationServerKey: urlBase64ToUint8Array('{{ config('settings.vapid_public_key') }}')
                                    }).then((newSubscription) => {
                                        @this.call('storePushSubscription', JSON.stringify(newSubscription));
                                        this.subscriptionStatus = 'subscribed';
                                    }).catch((e) => {
                                        if (Notification.permission === 'denied') {
                                            this.subscriptionStatus = 'denied';
                                        } else {
                                            console.error('Failed to subscribe the user: ', e);
                                            this.subscriptionStatus = 'not_subscribed';
                                        }
                                    });
                                });
                            });
                        } else {
                            this.subscriptionStatus = 'not_supported';
                        }
                    }
                }));
                function urlBase64ToUint8Array(base64String) {
                    const padding = '='.repeat((4 - base64String.length % 4) % 4);
                    const base64 = (base64String + padding)
                        .replace(/\-/g, '+')
                        .replace(/_/g, '/');

                    const rawData = window.atob(base64);
                    const outputArray = new Uint8Array(rawData.length);

                    for (let i = 0; i < rawData.length; ++i) {
                        outputArray[i] = rawData.charCodeAt(i);
                    }
                    return outputArray;
                }

            </script>
            @endscript
        @endif
        <div class="overflow-x-auto">
            <table class="w-full bg-background-secondary rounded-lg">
                <thead>
                    <tr class="border-b border-neutral/20">
                        <th class="text-left py-4 px-6 text-primary font-medium">
                            {{ __('account.notification') }}
                            <p class="text-sm text-base/70 font-normal mt-1">
                                {{ __('account.notifications_description') }}
                            </p>
                        </th>
                        <th class="text-center py-4 px-4 text-primary font-medium">
                            <div class="flex items-center justify-center gap-2">
                                <x-ri-mail-line class="size-4" />
                                <span>{{ __('account.email_notifications') }}</span>
                            </div>
                        </th>
                        <th class="text-center py-4 px-4 text-primary font-medium">
                            <div class="flex items-center justify-center gap-2">
                                <x-ri-notification-line class="size-4" />
                                <span>{{ __('account.in_app_notifications') }}</span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody x-data="{ preferences: $wire.entangle('preferences') }">
                    @foreach($this->notifications as $notification)
                    <tr class="border-b border-neutral/10 hover:bg-background/50 transition-colors">
                        <td class="py-4 px-6 text-base/70">
                            {{ $notification->name }}
                        </td>
                        <td class="py-4 px-4 text-center">
                            <button type="button" 
                                @click="preferences['{{ $notification->key }}']['mail_enabled'] = !preferences['{{ $notification->key }}']['mail_enabled']"
                                @if(!$notification->mail_controllable) disabled @endif
                                class="inline-flex cursor-pointer items-center p-2 rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-secondary disabled:opacity-50 disabled:cursor-not-allowed"
                                :class="preferences['{{ $notification->key }}']['mail_enabled'] ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 hover:bg-gray-500'">
                                <template x-if="preferences['{{ $notification->key }}']['mail_enabled']">
                                    <x-ri-mail-fill class="size-4 text-white" />
                                </template>
                                <template x-if="!preferences['{{ $notification->key }}']['mail_enabled']">
                                    <x-ri-mail-line class="size-4 text-white" />
                                </template>
                            </button>
                        </td>
                        <td class="py-4 px-4 text-center">
                            <button type="button" 
                                @click="preferences['{{ $notification->key }}']['in_app_enabled'] = !preferences['{{ $notification->key }}']['in_app_enabled']"
                                @if(!$notification->in_app_controllable) disabled @endif
                                class="inline-flex cursor-pointer items-center p-2 rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-secondary disabled:opacity-50 disabled:cursor-not-allowed"
                                :class="preferences['{{ $notification->key }}']['in_app_enabled'] ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 hover:bg-gray-500'">
                                <template x-if="preferences['{{ $notification->key }}']['in_app_enabled']">
                                    <x-ri-notification-fill class="size-4 text-white" />
                                </template>
                                <template x-if="!preferences['{{ $notification->key }}']['in_app_enabled']">
                                    <x-ri-notification-line class="size-4 text-white" />
                                </template>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-button.primary wire:click="savePreferences" class="w-full mt-6">
            {{ __('general.save') }}
        </x-button.primary>
    </div>
</div>