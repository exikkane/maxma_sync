{if $user_data.maxma_data}
    <div class="maxma-widget">
        <h3>Баланс бонусов</h3>
        {if isset($user_data.maxma_data.balance)}
            <div class="maxma-balance">
                <p><strong>Доступно:</strong> {$user_data.maxma_data.balance.balance}</p>
                <p><strong>Ожидают начисления:</strong> {$user_data.maxma_data.balance.pending_bonuses}</p>
                <p><small>Обновлено: {$user_data.maxma_data.balance.updated_at}</small></p>
            </div>
        {else}
            <p>Баланс недоступен</p>
        {/if}

        <h3>История начислений</h3>
        {if isset($user_data.maxma_data.history)}
            <table class="maxma-history">
                <thead>
                <tr>
                    <th>Дата</th>
                    <th>Сумма</th>
                    <th>Операция</th>
                    <th>Описание</th>
                </tr>
                </thead>
                <tbody>
                {foreach from=$user_data.maxma_data.history item=entry}
                    {if isset($entry.date)}
                        <tr>
                            <td>{$entry.date}</td>
                            <td>{$entry.amount}</td>
                            <td>{$entry.operation}</td>
                            <td>{$entry.operation_name}</td>
                        </tr>
                    {/if}
                {/foreach}
                </tbody>
            </table>
        {else}
            <p>История бонусов отсутствует</p>
        {/if}
    </div>

    <style>
        .maxma-widget {
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #f9f9f9;
        }
        .maxma-balance p {
            margin: 5px 0;
        }
        .maxma-history {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .maxma-history th, .maxma-history td {
            border: 1px solid #ccc;
            padding: 5px 10px;
            text-align: left;
        }
        .maxma-history th {
            background-color: #eee;
        }
    </style>
{/if}