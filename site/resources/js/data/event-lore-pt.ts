import type { EventLoreSet } from '@/data/event-lore';

/** The "Learn more" texts of resources/js/data/event-lore.ts in Brazilian Portuguese; keep both in step. */
export const EVENT_LORE_PT: EventLoreSet = {
    olympiad: {
        lore: [
            'Quando os aventureiros mais corajosos aprenderam os segredos dos Gigantes e se tornaram Noblesse, Aden precisou de um jeito de coroar os melhores entre eles. Assim nasceu a Grande Olimpíada: uma arena, um oponente, e só a habilidade decide.',
            'Cada temporada termina com um novo Herói para cada classe, cujo nome é lembrado por toda a terra.',
        ],
        howItWorks: [
            'Só Noblesse podem competir. Inscreva-se com um Olympiad Manager enquanto a arena estiver aberta: às sextas e sábados, a partir das 20:00, por 4 horas (horário do servidor).',
            'Lute em partidas por classe ou abertas a todas as classes para ganhar pontos; são necessárias pelo menos 10 partidas na temporada para concorrer a Herói.',
            'No fim de cada temporada, o melhor Noblesse de cada classe se torna Herói, com armas de Herói, habilidades de Herói e uma aura brilhante.',
        ],
        chapter: { href: '/lore#chaos', label: 'Capítulo VII: A Era do Caos' },
    },
    siege: {
        lore: [
            'Desde a Batalha pelo Castelo de Giran, as antigas regras de cerco são lei: quem toma um castelo se torna seu senhor legítimo, seja qual for seu berço. Os reis de Aden só podem confirmar o resultado.',
            'Nesta era, apenas dois tronos estão em disputa: Giran e Goddard. Cada um traz um tesouro, uma habilidade de dono do castelo e o respeito, ou a inveja, de todos os clãs de Aden.',
        ],
        howItWorks: [
            'Clãs de nível 3 ou superior se inscrevem como atacantes ou defensores antes do cerco. Os cercos acontecem a cada duas semanas.',
            'Um cerco dura 60 minutos. Os atacantes precisam romper muralhas e portões, e o líder do clã precisa gravar o selo do clã no Artefato Sagrado do castelo.',
            'O clã que domina o castelo quando o tempo acaba o governa até o próximo cerco, e o Arauto da Vitória vem recompensar seu líder.',
        ],
        chapter: { href: '/lore#frost', label: 'Capítulo X: Gelo e Vento' },
    },
    raid: {
        lore: [
            'Campeões caídos, feras antigas e generais de guerras esquecidas vagam pelas terras selvagens de Aden. São mais fortes que qualquer aventureiro sozinho, e guardam tesouros à altura.',
        ],
        howItWorks: [
            'Os raid bosses ficam em lugares fixos pelo mundo, do nível 20 ao fim de jogo. Reúna um grupo, encontre um do seu nível e derrube-o.',
            'Eles dropam materiais raros, receitas e equipamentos, e dão Raid Points.',
            'Um raid boss derrotado volta um ou dois dias depois. A página Sobre mostra quando os mais recentes devem voltar.',
        ],
    },
    seasonal: {
        SquashEvent: {
            lore: ['O jardineiro Fordson de Floran semeou suas sementes mágicas por mais um outono, e no Halloween elas crescem estranhas. Seu gato, Puss, procura heróis para cuidar das abóboras, e o que nascer dentro delas é seu.'],
            howItWorks: [
                'Monstros de toda Aden podem deixar cair Néctar. Troque com Puss the Cat na cidade: 10 por uma semente, 20 por uma semente grande, ou 100 ou mais por um instrumento chrono.',
                'Clique duas vezes na semente para plantá-la e use Néctar na abóbora jovem até ela mudar. Ela pode ficar madura ou murcha, e as maduras guardam prêmios melhores. As abóboras somem depois de alguns minutos, então seja rápido.',
                'Quebre a abóbora para pegar os prêmios. Abóboras grandes só podem ser quebradas com um instrumento chrono e podem conter Halloween Kits.',
            ],
        },
        HolidayOutOfSchedule: {
            lore: ['O Papai Noel veio passar o inverno em Aden, mas no caminho um feitiço transformou sua rena, Rudolph, em homem. Sem ninguém para puxar o trenó, o Papai Noel percorre os campos de caça a pé, e Rudolph manda os heróis atrás dele.'],
            howItWorks: [
                'Fale com o Humanized Rudolph em qualquer cidade. Todo dia às 12:00 e às 19:00 (horário de Brasília), o Papai Noel aparece por uma hora em um campo de caça diferente, e Rudolph pode teleportar você até ele.',
                'Peça seu presente ao Papai Noel: um L-Coin Pouch, um Giant\'s Shining Relic Pouch, um New Year Gift Box, Dye Powder e 10 de cada Scroll: Boost Attack e Scroll: Boost Defense. Um presente por conta por dia; o dia vira às 21:00 de Brasília (00:00 UTC).',
                'Fique perto de uma árvore de Natal, em Aden, em Giran ou ao lado do Papai Noel, para receber a Holiday Blessing: +100 de HP e MP e +10% de XP e SP por 30 minutos.',
            ],
        },
        LeonasDungeonEvent: {
            lore: [
                'Leona Blackbird é uma guerreira lendária. Sua masmorra é um espaço que se desdobra com seu poder e sua memória: uma reencenação do momento em que ela despertou, onde cada batalha treina aventureiros para se tornarem heróis.',
                'Quem não pode empunhar sua lança (magos, curandeiros e Sylphs) pode pegar a arma dela emprestada ou até assumir a sua forma enquanto luta lá dentro.',
            ],
            howItWorks: [
                'Fale com Leona Blackbird em Giran ou Aden. É preciso entrar como canal de comando de 2 a 40 jogadores, todos de nível 76 ou superior.',
                'Lá dentro, escolha a dificuldade (iniciante, intermediária ou avançada) e resista por 30 minutos. Leona pode emprestar sua arma, transformar você ou conceder sua bênção por 50.000 Adena.',
                'Sua pontuação entra no ranking: peça a Leona o Ranking da Masmorra para ver os 20 melhores. Você pode entrar uma vez por dia; a entrada é liberada todas as manhãs às 06:30 (horário do servidor).',
            ],
        },
        LegendOfTheWaterTemple: {
            lore: [
                'A Árvore Mãe dos Elfos está enfraquecendo. Seus pesquisadores descobriram que a Maravilhosa Água da Vida poderia restaurá-la, mas o Templo da Água foi invadido por monstros que se passam por Espíritos da Água e dizem seguir Eva.',
                'No topo do templo aguardam Aber, Adepta de Eva, e Elle, que se diz a Deusa da Água.',
            ],
            howItWorks: [
                'Fale com a Pesquisadora da Árvore Mãe Adella em Giran ou Aden, com um grupo de 2 a 5 jogadores de nível 10 ou superior.',
                'O Inspetor Aos envia você para o campo de batalha. Derrote monstros suficientes em cada andar para subir, e cuidado: o templo pode inundar. Você tem 30 minutos.',
                'Fique perto do chefe quando ele cair para receber sua recompensa. Você pode entrar uma vez por dia; a entrada é liberada todas as manhãs às 06:30 (horário do servidor).',
            ],
            chapter: { href: '/lore#gods', label: 'Capítulo II: Filhos dos Deuses' },
        },
        LetterCollector: {
            lore: ['Letras foram espalhadas pelas terras de Aden. Sozinhas não significam nada; juntas, formam palavras que liberam tesouros.'],
            howItWorks: [
                'Monstros de nível 40 ou mais podem dropar pacotes de palavras H.O.T, S.U.M.M.E.R e LINEAGE2, cheios de letras.',
                'Abra a janela do Coletor de Letras e forme HOT, SUMMER ou LINEAGE II com suas letras para trocá-las pelo pacote de recompensa da palavra.',
            ],
        },
    },
    epic: {
        '29068': {
            lore: [
                'Antharas, o Dragão da Terra, é filho de Shilen e Gran Kain. Quando Shilen foi derrotada, ele se escondeu sob o Vale do Dragão perto de Giran, onde dorme à espera do retorno dela.',
                'Desde a era de Dwelling of Spirits seu covil está aberto a toda Aden, e sempre que o chão do vale treme, o povo de Giran olha para as montanhas.',
            ],
            howItWorks: [
                'Reúna um exército no Vale do Dragão e entre em seu covil. Ele é um dos seres mais fortes de Aden: só alianças bem preparadas sobrevivem.',
                'Se ele cair, volta dias depois. O card mostra quando.',
            ],
            chapter: { href: '/lore#shilen', label: 'Capítulo IV: A Traição de Shilen' },
        },
        '29020': {
            lore: [
                'O Imperador Baium de Elmoreden ergueu a Torre da Insolência para alcançar os próprios deuses. Os deuses responderam selando-o no topo, congelado em seu próprio monumento.',
                'Seu império se partiu em dois quando ele caiu: o Reino de Aden e o Reino de Elmore.',
            ],
            howItWorks: [
                'Suba a Torre da Insolência até o último andar, onde Baium está congelado em seu trono. Despertá-lo começa a luta.',
                'Ele luta ao lado de seus Arcanjos. Traga um grupo de raid completo.',
                'Depois de derrotado, ele volta dias depois. O card mostra quando.',
            ],
            chapter: { href: '/lore#baium', label: 'Capítulo VI: A Torre da Insolência' },
        },
        '29022': {
            lore: ['Zaken, o capitão pirata amaldiçoado, navega pelos mares ao redor de sua ilha com uma tripulação que nem a morte conseguiu deter. Quem aborda seu navio enfrenta os mortos e os vivos.'],
            howItWorks: ['Encontre Zaken onde seu navio está ancorado e abra caminho por sua tripulação. Um alvo para grupos e clãs fortes; se derrotado, ele volta depois. O card mostra quando.'],
        },
        '29001': {
            lore: ['Sob os campos de Aden há um vasto Ninho das Formigas. Em seu coração, a Queen Ant gera uma cria sem fim, protegida por suas enfermeiras e guardas reais.'],
            howItWorks: [
                'Atravesse o Ninho das Formigas até a câmara dela. Mate as enfermeiras primeiro, ou elas vão curá-la.',
                'Um alvo clássico para clãs de nível médio. Se derrotada, ela volta um ou dois dias depois.',
            ],
        },
        '29006': {
            lore: ['A Torre de Cruma, relíquia de uma era antiga, ainda zumbe com máquinas ancestrais. Em seu núcleo aguarda Core, protegido pelos autômatos que o servem.'],
            howItWorks: ['Desça pela Torre de Cruma até a câmara do núcleo. Um alvo para clãs de nível médio; depois de destruído, volta um ou dois dias depois.'],
        },
        '29014': {
            lore: ['Orfen, a Senhora dos Esporos, assombra o Mar de Esporos perto de Oren, uma criatura nascida do veneno que paira sobre aquela terra.'],
            howItWorks: ['Orfen vaga pelo Mar de Esporos com sua escolta. Um alvo para clãs de nível médio; se derrotada, volta um ou dois dias depois.'],
        },
    },
};
