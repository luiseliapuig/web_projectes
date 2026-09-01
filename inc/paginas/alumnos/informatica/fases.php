<?php
declare(strict_types=1);

// Definició de les fases de l'arquitectura 'informatica'. Única font de
// veritat sobre quines fases existeixen, el seu ordre i com localitzar-les
// (per a navegació i resolució). NO parametritza el contingut de cada fase:
// cada fase-N.php conserva el seu propi PHP/HTML/lògica específica, tal com
// ja funcionava abans d'aquesta migració.
//
// 'titulo' és el mateix text que ja feia servir fases_navegacion.php per a
// les pastilles de navegació (inclosos els salts de línia \n originals per
// al retall visual). 'ruta' i 'main' són la URL pública i la clau del router
// ja existents; 'archivo' és només informatiu (el nom del fitxer principal).
//
// 'descripcio' és el mateix resum que ja mostrava cada fase-N.php en entrar
// (abans $faseIntroduccion hardcodejat allà mateix): única font, perquè la
// capçalera interior de la fase i la targeta-resum de "Fases del projecte"
// (fases_projecte.php) mai puguin mostrar textos diferents ni divergir amb
// el temps. Encara buida per a Fase 3-7 (no hi ha cap redacció real
// existent per reutilitzar-hi; no s'inventa contingut nou).
return [
    1 => [
        'titulo' => "Pluja d’idees\ni formació de grups",
        'descripcio' => 'En aquesta primera fase començareu a explorar possibles idees de projecte i a formar els grups de treball. L’objectiu és identificar una proposta interessant i començar a definir amb qui desenvolupareu el projecte al llarg del curs.',
        'ruta' => '/fases-del-projecte/fase-1',
        'main' => 'alumne-fase-1',
        'archivo' => 'fase-1.php',
    ],
    2 => [
        'titulo' => 'Proposta de projecte',
        'descripcio' => 'Definiu la idea inicial del projecte, el seu abast i els objectius principals. La proposta haurà de ser validada pel tutor abans de continuar.',
        'ruta' => '/fases-del-projecte/fase-2',
        'main' => 'alumne-fase-2',
        'archivo' => 'fase-2.php',
    ],
    3 => [
        'titulo' => 'Document funcional',
        'descripcio' => 'Definiu amb claredat què voleu construir, a qui s’adreça el projecte, quins requisits ha de complir i quines funcionalitats tindrà abans de començar el desenvolupament.',
        'ruta' => '/fases-del-projecte/fase-3',
        'main' => 'alumne-fase-3',
        'archivo' => 'fase-3.php',
    ],
    4 => [
        'titulo' => 'Planificació i gestió',
        'descripcio' => 'Planifiqueu les principals etapes del projecte i prepareu el tauler que fareu servir per organitzar i seguir el treball durant el desenvolupament.',
        'ruta' => '/fases-del-projecte/fase-4',
        'main' => 'alumne-fase-4',
        'archivo' => 'fase-4.php',
    ],
    5 => [
        'titulo' => "Desenvolupament\ndel projecte",
        'descripcio' => 'Aquesta és la fase on el vostre projecte començarà a prendre forma i evolucionarà fins a convertir-se en un producte final preparat per posar-lo en producció.',
        'ruta' => '/fases-del-projecte/fase-5',
        'main' => 'alumne-fase-5',
        'archivo' => 'fase-5.php',
    ],
    6 => [
        'titulo' => 'Memòria',
        'descripcio' => 'La memòria recull el desenvolupament del projecte i explica què heu fet, com ho heu fet i per què heu pres les decisions que l’han fet evolucionar.',
        'ruta' => '/fases-del-projecte/fase-6',
        'main' => 'alumne-fase-6',
        'archivo' => 'fase-6.php',
    ],
    7 => [
        'titulo' => 'Defensa',
        'descripcio' => 'Prepareu la defensa del projecte i la presentació que utilitzareu per explicar el vostre treball davant del tribunal.',
        'ruta' => '/fases-del-projecte/fase-7',
        'main' => 'alumne-fase-7',
        'archivo' => 'fase-7.php',
    ],
];
