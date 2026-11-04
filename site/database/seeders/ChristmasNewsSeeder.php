<?php

namespace Database\Seeders;

use App\Models\NewsPost;
use Illuminate\Database\Seeder;

/**
 * The winter event (HolidayOutOfSchedule): Santa's daily gift. Published on the event's first day, and fills the
 * home page banner until it ends on 6 January.
 * Run once with php artisan db:seed --class=ChristmasNewsSeeder --force.
 */
class ChristmasNewsSeeder extends Seeder
{
    public function run(): void
    {
        NewsPost::updateOrCreate(['slug' => 'santa-visits-aden'], [
            'category' => 'event',
            'title_en' => 'Santa Is Lost in Aden',
            'title_pt' => 'O Papai Noel Se Perdeu em Aden',
            'summary_en' => 'A spell turned Rudolph into a man, so Santa walks the hunting grounds on foot. Find him twice a day for a free gift, until 6 January.',
            'summary_pt' => 'Um feitiço transformou Rudolph em homem, e o Papai Noel agora anda a pé pelos campos de caça. Encontre-o duas vezes por dia e ganhe um presente, até 6 de janeiro.',
            'body_en' => <<<'MD'
                Snow is falling on Giran, and an odd man with antlers is standing in the square. He swears he is Rudolph. A spell caught him on the road to Aden, and without his reindeer Santa Claus has to deliver every gift on foot. He has terrible eyesight, so he keeps getting lost.

                ## How to play

                1. **Find Humanized Rudolph** in any town.
                2. **Come back at Santa's hour:** every day at 15:00 and 22:00 UTC (server time, see [the server clock](/about#status)), Santa appears for one hour in a different hunting ground.
                3. **Let Rudolph teleport you** to him, and **ask for your gift**.

                Each gift holds an L-Coin Pouch, a Giant's Shining Relic Pouch, a New Year Gift Box with Christmas Transformation Scrolls, Dye Powder, and 10 each of Scroll: Boost Attack and Scroll: Boost Defense. You get one gift per account each day.

                Stand near a Christmas tree in Aden, in Giran or beside Santa for the Holiday Blessing: +100 HP and MP and +10% XP and SP for 30 minutes.

                Santa stays until 6 January, and every hero can join at any level. No hero yet? [Create one](/register) and be in Aden tonight.

                So, hero: can you find Santa before he wanders off again?
                MD,
            'body_pt' => <<<'MD'
                A neve cai sobre Giran, e um sujeito esquisito com chifres está parado na praça. Ele jura que é o Rudolph. Um feitiço o pegou na estrada para Aden, e sem sua rena o Papai Noel tem que entregar cada presente a pé. Ele enxerga muito mal, então vive se perdendo.

                ## Como jogar

                1. **Encontre o Humanized Rudolph** em qualquer cidade.
                2. **Volte na hora do Papai Noel:** todo dia às 12:00 e às 19:00 (horário de Brasília), o Papai Noel aparece por uma hora em um campo de caça diferente.
                3. **Deixe o Rudolph teleportar você** até ele e **peça seu presente**.

                Cada presente traz um L-Coin Pouch, um Giant's Shining Relic Pouch, um New Year Gift Box com Christmas Transformation Scrolls, Dye Powder e 10 de cada Scroll: Boost Attack e Scroll: Boost Defense. É um presente por conta por dia.

                Fique perto de uma árvore de Natal em Aden, em Giran ou ao lado do Papai Noel para receber a Holiday Blessing: +100 de HP e MP e +10% de XP e SP por 30 minutos.

                O Papai Noel fica até 6 de janeiro, e qualquer herói pode participar, em qualquer nível. Ainda não tem um herói? [Crie o seu](/register) e esteja em Aden hoje à noite.

                E então, herói: você consegue achar o Papai Noel antes que ele se perca de novo?
                MD,
            'published_at' => '2026-12-12 00:00:00',
            'is_featured' => true,
            'featured_until' => '2027-01-06 23:59:59',
            'banner_theme' => 'winter',
        ]);
    }
}
