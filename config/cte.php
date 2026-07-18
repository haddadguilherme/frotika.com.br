<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Prazo padrão de recebimento (dias)
    |--------------------------------------------------------------------------
    |
    | Usado para calcular o `due_date` do lançamento de receita gerado a partir
    | do CT-e (competência = data de emissão; vencimento = emissão + N dias).
    |
    */

    'receivable_days' => (int) env('CTE_RECEIVABLE_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Percentual padrão do frete repassado ao agregado
    |--------------------------------------------------------------------------
    |
    | A empresa do sistema costuma ser a transportadora subcontratada
    | (agregado). O crédito no fluxo de caixa é uma fatia do vTPrest negociada
    | com a contratante (emitente do CT-e). Quando a contratante não tem um
    | percentual próprio cadastrado, usa-se este padrão. 100 = frete cheio
    | (transporte direto, quando a empresa é a própria emitente).
    |
    */

    'default_freight_share_percent' => (float) env('CTE_DEFAULT_FREIGHT_SHARE_PERCENT', 100),

    /*
    |--------------------------------------------------------------------------
    | Disco e caminho do XML privado por grupo
    |--------------------------------------------------------------------------
    |
    | Caminho relativo: grupos/{group_uuid}/cte/{YYYY}/{MM}/{chave}.xml
    |
    */

    'storage_disk' => env('CTE_STORAGE_DISK', 'local'),
];
