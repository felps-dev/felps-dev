<?php
declare(strict_types=1);

/** First-run content. Rows: tasks/shopping [title, room, priority]; inventory [title, place, room, decision, notes]. */
function seed_rows(): array
{
    return [
        'tasks' => [
            ['Achar um pintor para a sala', 'Sala', 0],
            ['Alguém para consertar os registros do banheiro', 'Banheiro', 0],
            ['Achar alguém para instalar ar split no escritório', 'Escritório', 0],
            ['Cortina de vidro para a varanda', 'Varanda', 1],
            ['Achar alguém / instalação do espelho da sala', 'Sala', 2],
        ],
        'shopping' => [
            ['Mesa para escritório', 'Escritório', 0],
            ['Ar split', 'Escritório', 0],
            ['Cortina para sala', 'Sala', 1],
            ['Cortina para escritório', 'Escritório', 0],
            ['Lustre', 'Sala', 10],
            ['Lâmpadas quentes', 'Todos', 1],
            ['Interruptores inteligentes', 'Todos', 1],
            ['Cama de parede', '', 0],
        ],
        'inventory' => [
            ['Microondas', 'recreio', 'Cozinha', 'sell', ''],
            ['Microondas', 'seropedica', 'Cozinha', 'keep', ''],
            ['TV Sala', 'seropedica', 'Sala', 'sell', ''],
            ['TV Sala', 'recreio', 'Sala', 'other', 'Usar para o escritório'],
            ['TV Quarto', 'seropedica', 'Quarto', 'keep', ''],
            ['Ar condicionado escritório', 'recreio', 'Escritório', 'other', 'Vender/trocar depois que colocar o split'],
            ['Ar condicionado quarto', 'recreio', 'Quarto', 'sell', 'Colocar o de Seropédica'],
            ['Ar condicionado quarto', 'seropedica', 'Quarto', 'keep', 'Colocar no quarto do Recreio'],
            ['Ar condicionado portátil', 'seropedica', 'Todos', 'sell', ''],
            ['Estante da sala', 'seropedica', 'Sala', 'sell', ''],
            ['Estante do Recreio', 'recreio', 'Sala', 'keep', ''],
            ['Fogão', 'recreio', 'Cozinha', 'sell', ''],
            ['Fogão', 'seropedica', 'Cozinha', 'keep', ''],
            ['Geladeira', 'recreio', 'Cozinha', 'sell', ''],
            ['Geladeira', 'seropedica', 'Cozinha', 'keep', ''],
            ['Máquina de lavar', 'seropedica', '', 'keep', ''],
            ['Cama escritório', 'recreio', 'Escritório', 'other', 'Vender depois que colocar cama de parede'],
            ['Cama quarto', 'recreio', 'Quarto', 'sell', ''],
            ['Cama quarto', 'seropedica', 'Quarto', 'keep', ''],
        ],
    ];
}
