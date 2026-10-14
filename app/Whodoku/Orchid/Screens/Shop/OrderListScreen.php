<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Screens\Shop;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Repository;
use Orchid\Screen\Screen;
use Orchid\Screen\Sight;
use Orchid\Screen\TD;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Throwable;
use Whodoku\Application\Payment\OrderFulfiller;
use Whodoku\Application\Payment\SalesSummary;
use Whodoku\Application\Payment\ShopService;
use Whodoku\Models\AdminAuditLog;
use Whodoku\Models\Order;

class OrderListScreen extends Screen
{
    private const array STATUSES = [
        Order::PENDING => 'ждёт оплаты', Order::PAID => 'оплачен', Order::CANCELED => 'отменён',
        Order::REFUNDED => 'возвращён', Order::FAILED => 'сбой (сумма не совпала)',
    ];

    /** @return array<string, mixed> */
    public function query(Request $request): iterable
    {
        $status = (string) $request->query('status', '');

        return [
            'summary' => new Repository((new SalesSummary)->overall()),
            'orders' => Order::query()->with(['player', 'events'])
                ->when(isset(self::STATUSES[$status]), fn ($query) => $query->where('status', $status))
                ->latest('id')->paginate(),
        ];
    }

    public function name(): ?string
    {
        return 'Заказы';
    }

    public function description(): ?string
    {
        return 'Покупки пакетов подсказок: статус, журнал ответов платёжной системы, ручная выдача и возврат.';
    }

    public function permission(): ?iterable
    {
        return ['platform.puzzles.players'];
    }

    public function layout(): iterable
    {
        return [
            Layout::legend('summary', [
                Sight::make('week', 'Выручка за 7 дней'),
                Sight::make('month', 'За 30 дней'),
                Sight::make('total', 'Всего'),
                Sight::make('payers', 'Платящих игроков'),
                Sight::make('average', 'Средний чек'),
                Sight::make('refund_share', 'Доля возвратов'),
                Sight::make('attention', 'Требуют внимания (ждут оплаты дольше часа)'),
            ])->title('Сводка'),
            Layout::rows([
                Select::make('status')->options(['' => 'Все'] + self::STATUSES)->title('Статус')->value(request()->query('status', '')),
                Button::make('Показать')->method('filter')->icon('bs.funnel'),
            ]),
            Layout::table('orders', [
                TD::make('id', '№')->render(fn (Order $order) => (string) $order->id),
                TD::make('created_at', 'Создан')->usingComponent(DateTimeSplit::class),
                TD::make('player', 'Игрок')->render(fn (Order $order) => $order->player->detective_name ?? '—'),
                TD::make('product', 'Товар')->render(fn (Order $order) => "{$order->product} ×{$order->quantity}".($order->provider === 'fake' ? ' (тест)' : '')),
                TD::make('amount', 'Сумма')->align(TD::ALIGN_RIGHT)->render(fn (Order $order) => ShopService::rubles($order->amount_kopecks).' ₽'),
                TD::make('status', 'Статус')->render(fn (Order $order) => self::STATUSES[$order->status] ?? $order->status),
                TD::make('journal', 'Журнал')->render(fn (Order $order) => ($event = $order->dispatchesEvents->last()) === null ? '—' : e($event->source.': '.$event->result)),
                TD::make('actions', '')->align(TD::ALIGN_RIGHT)->cantHide()->width('4rem')->render(fn (Order $order) => DropDown::make()->icon('bs.three-dots-vertical')->list([
                    Button::make('Выдать вручную')->icon('bs.gift')
                        ->confirm('Подсказки будут выданы без проверки у платёжной системы. Используйте, только если оплата подтверждена. Продолжить?')
                        ->canSee(in_array($order->status, [Order::PENDING, Order::FAILED], true))->method('grant', ['order' => $order->public_id]),
                    Button::make('Вернуть деньги')->icon('bs.arrow-counterclockwise')->type(Color::DANGER)
                        ->confirm('Платёжная система вернёт всю сумму, купленные подсказки будут сняты. Продолжить?')
                        ->canSee($order->status === Order::PAID)->method('refund', ['order' => $order->public_id]),
                ])),
            ]),
        ];
    }

    public function filter(Request $request): RedirectResponse
    {
        return to_route('platform.orders', array_filter(['status' => $request->input('status')]));
    }

    public function grant(Request $request, Order $order, OrderFulfiller $fulfiller): RedirectResponse
    {
        abort_unless($request->user()->hasAccess('platform.puzzles.players'), 403);

        $fulfiller->grantManually($order, 'выдано администратором '.$request->user()->email);
        $this->audit($request, 'order_grant', $order);
        Toast::success('Подсказки выданы.');

        return to_route('platform.orders');
    }

    public function refund(Request $request, Order $order, OrderFulfiller $fulfiller): RedirectResponse
    {
        abort_unless($request->user()->hasAccess('platform.puzzles.players'), 403);

        try {
            $fulfiller->refund($order);
            $this->audit($request, 'order_refund', $order);
            Toast::success('Возврат оформлен, подсказки сняты.');
        } catch (Throwable $e) {
            report($e);
            Toast::error('Платёжная система не приняла возврат: '.$e->getMessage());
        }

        return to_route('platform.orders');
    }

    private function audit(Request $request, string $action, Order $order): void
    {
        AdminAuditLog::query()->create([
            'user_id' => $request->user()->id, 'actor_email' => $request->user()->email, 'action' => $action,
            'validation_result' => ['order_id' => $order->id, 'player_id' => $order->player_id],
        ]);
    }
}
