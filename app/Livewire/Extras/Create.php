<?php

declare(strict_types=1);

namespace App\Livewire\Extras;

use App\Domain\Extras\Actions\RecordExtraOrder;
use App\Domain\Tenancy\TenantContext;
use App\Livewire\Admin\Hotels\Create as AdminHotelsCreate;
use App\Models\ExtraService;
use App\Models\Stay;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
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

    public string $currency = '';

    public string $orderDate = '';

    /** @var list<array{extra_service_id: string, quantity: string, price: string}> */
    public array $lines = [];

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('manage-folios') ?? false, 403);

        $this->currency = app(TenantContext::class)->current()->currency;
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
     * @return list<string>
     */
    #[Computed]
    public function currencies(): array
    {
        return AdminHotelsCreate::CURRENCIES;
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

    public function save(RecordExtraOrder $recordExtraOrder): void
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
            'currency' => ['required', 'in:'.implode(',', $this->currencies())],
            'orderDate' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.extra_service_id' => ['required', 'integer', 'exists:extra_services,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.price' => ['required', 'numeric', 'min:0'],
        ], [
            'customer.required' => __('Select a customer.'),
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
            $order = $recordExtraOrder->execute(
                stay: $stay,
                currency: $this->currency,
                orderDate: CarbonImmutable::parse($this->orderDate),
                lines: array_map(fn (array $line): array => [
                    'extra_service_id' => (int) $line['extra_service_id'],
                    'quantity' => (int) $line['quantity'],
                    'unit_price' => Money::fromMajor((float) $line['price'], $this->currency)->amount,
                ], $this->lines),
                createdBy: Auth::user(),
            );
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Extras order :reference saved.', ['reference' => $order->reference]));

        $this->customer = '';
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
