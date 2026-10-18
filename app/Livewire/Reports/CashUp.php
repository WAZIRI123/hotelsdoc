<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Domain\Folios\BillSection;
use App\Domain\Reports\FolioPaymentShare;
use App\Domain\Reports\ReportCatalog;
use App\Models\Expense;
use App\Models\FolioCharge;
use App\Models\Payment;
use App\Models\PosOrderItem;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Support\Money;
use App\Support\Vat;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Get Report')]
class CashUp extends Component
{
    public const VIEWS = ['reservations', 'staff', 'general'];

    /**
     * The ReportCatalog entry that grants access to each tab.
     *
     * @var array<string, string>
     */
    public const VIEW_REPORTS = [
        'reservations' => 'reservations',
        'staff' => 'payments',
        'general' => 'payments',
    ];

    #[Url(as: 'date')]
    public string $date = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $view = 'reservations';

    public function boot(): void
    {
        abort_if($this->allowedViews() === [], 403);
    }

    public function mount(): void
    {
        if ($this->date === '') {
            $this->date = CarbonImmutable::today()->toDateString();
        }

        if ($this->to === '') {
            $this->to = $this->date;
        }

        $this->showView($this->view);
    }

    public function showView(string $view): void
    {
        $allowed = $this->allowedViews();

        $this->view = in_array($view, $allowed, true) ? $view : $allowed[0];
    }

    /**
     * The tabs the current user may open, in display order.
     *
     * @return list<string>
     */
    public function allowedViews(): array
    {
        return array_values(array_filter(
            self::VIEWS,
            fn (string $view): bool => ReportCatalog::allows(Auth::user(), self::VIEW_REPORTS[$view]),
        ));
    }

    public function getReport(): void
    {
        $this->validate([
            'date' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:date'],
        ]);

        unset($this->reservationRows, $this->payments, $this->expensesPaidOut, $this->discountsGiven);
    }

    /**
     * Reservations in-house at any point in the selected range (arriving, staying or departing);
     * cancelled bookings and no-shows are left out.
     *
     * @return array<int, array{customer: string, rooms: string, check_in: string, nights: int, per_night: Money, discount: Money, total: Money, paid_by: string, paid: Money, balance: Money, status: string}>
     */
    #[Computed]
    public function reservationRows(): array
    {

        $reservations = Reservation::query()
            ->with(['guest', 'rooms.room', 'rooms.roomType', 'rooms.nights', 'stay.folio.charges', 'stay.folio.payments'])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereDate('arrival', '<=', $this->reportEnd()->toDateString())
            ->whereDate('departure', '>=', $this->reportDay()->toDateString())
            ->orderBy('arrival')
            ->get();

        return $reservations->map(function (Reservation $reservation): array {
            $folio = $reservation->stay?->folio;

            $perNight = $reservation->rooms->reduce(
                fn (Money $carry, ReservationRoom $room): Money => $carry->add($room->nightly_rate),
                Money::zero(),
            );
            $roomTotal = $reservation->rooms->reduce(
                fn (Money $carry, ReservationRoom $room): Money => $carry->add($room->totalRate()),
                Money::zero(),
            );
            $discount = Money::fromMinor((int) abs((int) $folio?->charges
                ->where('source_type', 'discount')
                ->sum(fn ($charge): int => $charge->total->amount)));
            // Room rates are entered prices; when VAT is added on top the guest is billed more.
            $total = Vat::current()->split($roomTotal)['gross']->subtract($discount);
            // The folio can also hold POS and extras, so the room bill is credited with what was paid on accommodation.
            $paid = FolioPaymentShare::paid($folio, $total, BillSection::Accommodation);

            return [
                'customer' => $reservation->guest?->fullName() ?? '—',
                'rooms' => $reservation->rooms
                    ->map(fn (ReservationRoom $room): string => $room->room === null ? $room->roomType->name : $room->room->number)
                    ->implode(', '),
                'check_in' => ($reservation->stay === null || $reservation->stay->checked_in_at === null
                    ? $reservation->arrival
                    : $reservation->stay->checked_in_at)->format('d-m-Y'),
                'nights' => (int) $reservation->arrival->diffInDays($reservation->departure),
                'per_night' => $perNight,
                'discount' => $discount,
                'total' => $total,
                'paid_by' => $folio?->payments
                    ->where('status', 'confirmed')
                    ->pluck('method_code')
                    ->unique()
                    ->map(fn (string $code): string => ucwords(str_replace('_', ' ', $code)))
                    ->implode(', ') ?? '',
                'paid' => $paid,
                'balance' => $total->subtract($paid),
                'status' => ucwords(str_replace('_', ' ', $reservation->status)),
            ];
        })->all();
    }

    /**
     * @return array{total: Money, paid: Money, balance: Money}
     */
    #[Computed]
    public function reservationTotals(): array
    {
        $totals = ['total' => Money::zero(), 'paid' => Money::zero(), 'balance' => Money::zero()];

        foreach ($this->reservationRows() as $row) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] = $totals[$key]->add($row[$key]);
            }
        }

        return $totals;
    }

    /**
     * @return Collection<int, Payment>
     */
    #[Computed]
    public function payments(): Collection
    {

        return Payment::query()
            ->with(['folio', 'receivedBy'])
            ->where('status', 'confirmed')
            ->whereBetween('confirmed_at', [$this->reportDay(), $this->reportEnd()])
            ->orderBy('confirmed_at')
            ->get();
    }

    /**
     * @return array<string, array{label: string, count: int, total: Money}>
     */
    #[Computed]
    public function totalsByMethod(): array
    {
        return $this->groupPayments(fn (Payment $payment): array => [
            $payment->method_code,
            ucwords(str_replace('_', ' ', $payment->method_code)),
        ]);
    }

    /**
     * @return array<string, array{label: string, count: int, total: Money}>
     */
    #[Computed]
    public function totalsByStaff(): array
    {
        return $this->groupPayments(fn (Payment $payment): array => [
            $payment->receivedBy === null ? 'unknown' : (string) $payment->receivedBy->id,
            $payment->receivedBy === null ? __('Unknown') : $payment->receivedBy->name,
        ]);
    }

    /**
     * Money in and out per payment method: payments collected less expenses
     * paid out with that method. Expenses with no method get their own row.
     *
     * @return array<string, array{label: string, count: int, collected: Money, paid_out: Money, net: Money}>
     */
    #[Computed]
    public function methodSummary(): array
    {
        $rows = [];

        foreach ($this->totalsByMethod() as $code => $group) {
            $rows[$code] = ['label' => $group['label'], 'count' => $group['count'], 'collected' => $group['total'], 'paid_out' => Money::zero(), 'net' => $group['total']];
        }

        foreach ($this->expensesPaidOut() as $expense) {
            $code = $expense->method_code ?? '';
            $rows[$code] ??= [
                'label' => $code === '' ? __('Method not recorded') : ucwords(str_replace('_', ' ', $code)),
                'count' => 0,
                'collected' => Money::zero(),
                'paid_out' => Money::zero(),
                'net' => Money::zero(),
            ];
            $rows[$code]['paid_out'] = $rows[$code]['paid_out']->add($expense->money());
            $rows[$code]['net'] = $rows[$code]['collected']->subtract($rows[$code]['paid_out']);
        }

        return $rows;
    }

    /**
     * What should be in hand per payment method and currency: foreign money
     * as the customer handed it over, with any change given back from the
     * hotel currency, alongside what was credited to the bills.
     *
     * @return array<string, array{method: string, currency: string, foreign: bool, count: int, received: Money, value: Money, change: Money, credited: Money}>
     */
    #[Computed]
    public function collectedByCurrency(): array
    {
        $rows = [];

        foreach ($this->payments() as $payment) {
            $tender = $payment->tendered();
            $received = $tender === null ? $payment->amount : $tender->amount;
            $key = $payment->method_code.'|'.$received->currency;

            $rows[$key] ??= [
                'method' => ucwords(str_replace('_', ' ', $payment->method_code)),
                'currency' => $received->currency,
                'foreign' => $tender !== null,
                'count' => 0,
                'received' => Money::zero($received->currency),
                'value' => Money::zero(),
                'change' => Money::zero(),
                'credited' => Money::zero(),
            ];

            $rows[$key]['count']++;
            $rows[$key]['received'] = $rows[$key]['received']->add($received);
            $rows[$key]['value'] = $rows[$key]['value']->add($tender?->localValue() ?? $payment->amount);
            $rows[$key]['change'] = $rows[$key]['change']->add($payment->changeGiven() ?? Money::zero());
            $rows[$key]['credited'] = $rows[$key]['credited']->add($payment->amount);
        }

        uasort($rows, fn (array $a, array $b): int => [$a['foreign'], $a['currency'], $a['method']] <=> [$b['foreign'], $b['currency'], $b['method']]);

        return $rows;
    }

    /**
     * Whether any payment in the period was taken in a foreign currency.
     */
    #[Computed]
    public function hasForeignPayments(): bool
    {
        return collect($this->collectedByCurrency())->contains('foreign', true);
    }

    /**
     * @return Collection<int, Expense>
     */
    #[Computed]
    public function expensesPaidOut(): Collection
    {
        return Expense::query()
            ->unvoided()
            ->whereDate('expense_date', '>=', $this->reportDay()->toDateString())
            ->whereDate('expense_date', '<=', $this->reportEnd()->toDateString())
            ->get();
    }

    #[Computed]
    public function totalPaidOut(): Money
    {
        return $this->expensesPaidOut()->reduce(
            fn (Money $carry, Expense $expense): Money => $carry->add($expense->money()),
            Money::zero(),
        );
    }

    /**
     * Every discount given in the period, oldest first: those taken off guest
     * bills and those given at the POS tills (line discounts and price cuts on
     * billed orders). Amounts are what came off the bill, VAT included.
     *
     * @return list<array{at: CarbonImmutable, where: string, customer: string, reason: string, by: string, amount: Money}>
     */
    #[Computed]
    public function discountsGiven(): array
    {
        $rows = FolioCharge::query()
            ->with(['folio.guest', 'postedBy'])
            ->where('source_type', 'discount')
            ->whereBetween('posted_at', [$this->reportDay(), $this->reportEnd()])
            ->whereHas('folio', fn (Builder $folio) => $folio->where('status', '!=', 'void'))
            ->get()
            ->map(fn (FolioCharge $charge): array => [
                'at' => CarbonImmutable::parse($charge->posted_at),
                'where' => __('Guest bill'),
                'customer' => $charge->folio?->guest?->fullName() ?? __('Walk-in'),
                'reason' => Str::after($charge->description, 'Discount — '),
                'by' => $charge->postedBy->name ?? '—',
                'amount' => Money::zero()->subtract($charge->total),
            ])
            ->all();

        $tillLines = PosOrderItem::query()
            ->with(['order.outlet', 'order.folio.guest', 'adjustedBy'])
            ->whereHas('order', fn (Builder $order) => $order
                ->where('status', 'billed')
                ->whereBetween('billed_at', [$this->reportDay(), $this->reportEnd()]))
            ->where('amount_off_gross', '>', 0)
            ->get();

        foreach ($tillLines as $line) {
            if ($line->order === null) {
                continue;
            }

            $rows[] = [
                'at' => CarbonImmutable::parse($line->order->billed_at),
                'where' => "{$line->order->outlet?->name} · {$line->order->reference}",
                'customer' => $line->order->folio?->guest?->fullName() ?? __('Walk-in'),
                'reason' => trim("{$line->quantity} × {$line->name_snapshot} — ".($line->adjustment_reason ?? ''), ' —'),
                'by' => $line->adjustedBy->name ?? '—',
                'amount' => $line->amount_off_gross,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        return $rows;
    }

    #[Computed]
    public function totalDiscountsGiven(): Money
    {
        return array_reduce($this->discountsGiven(), fn (Money $carry, array $row): Money => $carry->add($row['amount']), Money::zero());
    }

    #[Computed]
    public function grandTotal(): Money
    {
        return $this->payments()->reduce(
            fn (Money $carry, Payment $p): Money => $carry->add($p->amount),
            Money::zero(),
        );
    }

    /**
     * The start of the selected range, falling back to today while the input is invalid.
     */
    public function reportDay(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->date)->startOfDay();
        } catch (InvalidFormatException) {
            return CarbonImmutable::today();
        }
    }

    /**
     * The end of the selected range, never before its start.
     */
    public function reportEnd(): CarbonImmutable
    {
        try {
            $end = CarbonImmutable::parse($this->to)->endOfDay();
        } catch (InvalidFormatException) {
            $end = $this->reportDay()->endOfDay();
        }

        return $end->max($this->reportDay()->endOfDay());
    }

    public function render(): View
    {
        $this->showView($this->view);

        return view('livewire.reports.cash-up');
    }

    /**
     * @param  callable(Payment): array{0: string, 1: string}  $keyAndLabel
     * @return array<string, array{label: string, count: int, total: Money}>
     */
    private function groupPayments(callable $keyAndLabel): array
    {
        $groups = [];
        foreach ($this->payments() as $payment) {
            [$key, $label] = $keyAndLabel($payment);
            $groups[$key] ??= ['label' => $label, 'count' => 0, 'total' => Money::zero()];
            $groups[$key]['count']++;
            $groups[$key]['total'] = $groups[$key]['total']->add($payment->amount);
        }

        return $groups;
    }
}
