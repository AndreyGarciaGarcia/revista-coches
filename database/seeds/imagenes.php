<?php

/**
 =====================================================================
  Mapa de imágenes del sitio
 =====================================================================
  Cada entrada es  clave => consulta de búsqueda en Wikimedia Commons.
  El script scripts/descargar-imagenes.php busca la fotografía más
  adecuada, la descarga a public/assets/img/…, guarda la URL de
  origen y las metadatos de licencia (autor y licencia) en
  database/seeds/imagenes-manifest.json.
 *
  Así el sitio es 100 % reproducible y toda imagen queda atribuida
  correctamente en public/assets/img/CREDITOS.md.
 *
  Claves de coches  = slug del vehículo en SeedCoches.
  Claves de noticias = slug del artículo en SeedNoticias.
 =====================================================================
 */

declare(strict_types=1);

return [
    // -----------------------------------------------------------------
    //  Fotografías de portada
    // -----------------------------------------------------------------
    'hero' => [
        'hero-principal'  => 'Ferrari 488 GTB side view',
        'hero-secundario' => 'Mercedes-AMG GT roadster',
        'hero-electrico'  => 'electric car charging station',
    ],

    // -----------------------------------------------------------------
    //  Catálogo de vehículos (una fotografía por modelo)
    // -----------------------------------------------------------------
    'cars' => [
        'bmw-serie-3'      => 'BMW 3 Series G20 sedan',
        'bmw-i4'           => 'BMW i4 eDrive40 gran coupé',
        'mercedes-clase-c' => 'Mercedes-Benz W206 C-Class sedan',
        'mercedes-eqe'     => 'Mercedes-Benz EQE sedan',
        'audi-a4'          => 'Audi A4 B9 sedan',
        'audi-q8-etron'    => 'Audi Q8 e-tron SUV',
        'vw-golf'          => 'Volkswagen Golf Mk8 hatchback',
        'vw-id4'           => 'Volkswagen ID.4 SUV',
        'toyota-corolla'   => 'Toyota Corolla E210 hatchback',
        'toyota-rav4'      => 'Toyota RAV4 XA50 SUV',
        'tesla-model-3'    => 'Tesla Model 3 2019',
        'tesla-model-y'    => 'Tesla Model Y SUV',
        'seat-leon'        => 'SEAT Leon Mk4 hatchback',
        'cupra-formentor'  => 'Cupra Formentor SUV',
        'cupra-born'       => 'Cupra Born electric',
        'renault-clio'     => 'Renault Clio V hatchback',
        'renault-megane'   => 'Renault Megane E-Tech electric',
        'peugeot-308'      => 'Peugeot 308 III hatchback',
        'peugeot-3008'     => 'Peugeot 3008 II SUV',
        'ford-puma'        => 'Ford Puma 2019 crossover',
        'ford-mach-e'      => 'Ford Mustang Mach-E',
        'hyundai-tucson'   => 'Hyundai Tucson NX4 SUV',
        'hyundai-ioniq5'   => 'Hyundai Ioniq 5 electric',
        'kia-niro'         => 'Kia Niro DE hybrid',
        'volvo-xc60'       => 'Volvo XC60 II SUV',
        'porsche-911'      => 'Porsche 992 Carrera S',
        'skoda-octavia'    => 'Skoda Octavia IV Combi',
        'mazda-cx5'        => 'Mazda CX-5 KF SUV',
        'dacia-duster'     => 'Dacia Duster II SUV',
        'nissan-qashqai'   => 'Nissan Qashqai J12 SUV',
    ],

    // -----------------------------------------------------------------
    //  Ilustraciones de artículos
    // -----------------------------------------------------------------
    'news' => [
        'futuro-electrico'        => 'electric vehicle charging station cars',
        'prueba-bmw-serie-3'      => 'BMW 3 Series Touring G21',
        'cupra-formentor-vz'      => 'Cupra Formentor rear view',
        'tesla-model-y-autonomia' => 'Tesla Model Y',
        'compra-ocasion-guia'     => 'used car dealership lot',
        'hibridos-enchufables'    => 'plug-in hybrid car charging',
        'formula-1-sostenible'    => 'Formula One car racing track',
        'clasicos-revalorizados'  => 'classic car 1960s coupe',
        'hidrogeno-futuro'        => 'hydrogen fuel cell vehicle',
        'asistentes-conduccion'   => 'car interior driving road',
        'dacia-duster-analisis'   => 'Dacia Duster off road',
        'comparativa-berlinas'    => 'sedan cars parked street',
        'mantenimiento-itv'       => 'car mechanic workshop repair',
        'mercado-ocasion-precios' => 'car showroom new cars',
        'seguridad-volvo'         => 'car safety crash test dummy',
        'mazda-mx5-aniversario'   => 'Mazda MX-5 convertible roadster',
    ],
];
