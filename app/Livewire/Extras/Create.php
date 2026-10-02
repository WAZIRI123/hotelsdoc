<?php

declare(strict_types=1);

namespace App\Livewire\Extras;

use App\Domain\Extras\Actions\RecordExtraOrder;
use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Payments\PaymentGatewayManager;
use App\Models\ExtraOrder;
use App\Models\ExtraService;
use App\Models\PaymentMethod;
use App\Models\Stay;
use App\Support\HotelCurrency;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Title('Add extras services')]
class Create extends Component
{
    public const WALK_IN = 'walk_in';

    /** Either self::WALK_IN or the id of an in-house stay. */
    public string $customer = '';

    public string $orderDate = '';

    /** Payment method code a walk-in customer pays with. */
    public string $paymentMethod = '';

    public string $paymentReference = '';

    /** @var list<array{extra_service_id: string, quantity: string, price: string}> */
    public array $lines = [];

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('manage-folios') ?? false, 403);

        $this->orderDate = now()->toDateString();
        $this->lines = [$this->blankLine()];
    }

    /**
     * @return Collection<int, Stay>
     */
    #[Computed]
    public function inHouseStays(): Collection
    {
        return Stay::query()
            ->whereNull('checked_out_at')
            ->with(['guest', 'reservation.rooms.room'])
            ->orderBy('checked_in_at', 'desc')
            ->get();
    }

    /**
     * @return Collection<int, ExtraService>
     */
    #[Computed]
    public function services(): Collection
    {
        return ExtraService::query()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    #[Computed]
    public function paymentMethods(): Collection
    {
        return PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    #[Computed]
    public function paymentRequiresReference(): bool
    {
        if ($this->paymentMethod === '') {
            return false;
        }

        try {
            return app(PaymentGatewayManager::class)->for($this->paymentMethod)->requiresReference();
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function addLine(): void
    {
        $this->lines[] = $this->blankLine();
    }

    public function removeLine(int $index): void
    {
        array_splice($this->lines, $index, 1);

        if ($this->lines === []) {
            $this->lines = [$this->blankLine()];
        }
    }

    /**
     * Pre-fill the price (and a quantity of 1) when an extra item is picked.
     */
    public function updatedLines(mixed $value, string $key): void
    {
        [$index, $field] = array_pad(explode('.', $key), 2, null);

        if ($field !== 'extra_service_id' || ! isset($this->lines[(int) $index])) {
            return;
        }

        $service = $this->services()->firstWhere('id', (int) $value);
        if ($service === null) {
            return;
        }

        $this->lines[(int) $index]['price'] = (string) $service->price->toMajor();

        if ($this->lines[(int) $index]['quantity'] === '') {
            $this->lines[(int) $index]['quantity'] = '1';
        }
    }

    public function save(RecordExtraOrder $recordExtraOrder, RecordPayment $recordPayment): void
    {
        abort_unless(Auth::user()?->can('manage-folios') ?? false, 403);

        $this->lines = array_values(array_filter(
            $this->lines,
            fn (array $line): bool => $line['extra_service_id'] !== '' || $line['quantity'] !== '' || $line['price'] !== '',
        ));

        if ($this->lines === []) {
            $this->lines = [$this->blankLine()];
        }

        $this->validate([
            'customer' => ['required', 'string'],
            'orderDate' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.extra_service_id' => ['required', 'integer', 'exists:extra_services,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.price' => ['required', 'numeric', 'min:0'],
            'paymentMethod' => [Rule::requiredIf($this->customer === self::WALK_IN), 'nullable', Rule::in($this->paymentMethods()->pluck('code')->all())],
            'paymentReference' => [Rule::requiredIf($this->customer === self::WALK_IN && $this->paymentRequiresReference()), 'nullable', 'string', 'max:100'],
        ], [
            'customer.required' => __('Select a customer.'),
            'paymentMethod.required' => __('Select how the customer paid.'),
            'paymentReference.required' => __('Enter the payment reference.'),
            'lines.*.extra_service_id.required' => __('Select an extra item.'),
        ]);

        $stay = null;
        if ($this->customer !== self::WALK_IN) {
            $stay = Stay::query()->whereNull('checked_out_at')->find((int) $this->customer);

            if ($stay === null) {
                $this->addError('customer', __('Select a valid customer.'));

                return;
            }
        }

        try {
            $order = DB::transaction(function () use ($recordExtraOrder, $recordPayment, $stay): ExtraOrder {
                $currency = HotelCurrency::current();

                $order = $recordExtraOrder->execute(
                    stay: $stay,
                    currency: $currency,
                    orderDate: CarbonImmutable::parse($this->orderDate),
                    lines: array_map(fn (array $line): array => [
                        'extra_service_id' => (int) $line['extra_service_id'],
                        'quantity' => (int) $line['quantity'],
                        'unit_price' => Money::fromMajor((float) $line['price'], $currency)->amount,
                    ], $this->lines),
                    createdBy: Auth::user(),
                );

                if ($order->isWalkIn() && $order->total_amount > 0) {
                    $recordPayment->execute(
                        folio: $order->folio,
                        methodCode: $this->paymentMethod,
                        amount: $order->total(),
                        reference: $this->paymentReference ?: null,
                        receivedBy: Auth::user(),
                    );
                }

                return $order;
            });
        } catch (RuntimeException|InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Extras order :reference saved.', ['reference' => $order->reference]));

        $this->reset(['customer', 'paymentMethod', 'paymentReference']);
        $this->lines = [$this->blankLine()];
    }

    public function render(): View
    {
        return view('livewire.extras.create');
    }

    /**
     * @return array{extra_service_id: string, quantity: string, price: string}
     */
    private function blankLine(): array
    {
        return ['extra_service_id' => '', 'quantity' => '', 'price' => ''];
    }
}
