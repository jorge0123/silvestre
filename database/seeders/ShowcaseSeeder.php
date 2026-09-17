<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\BusinessKind;
use App\Enums\BusinessRole;
use App\Enums\FulfillmentMethod;
use App\Enums\OrderState;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PlanCode;
use App\Enums\PriceMode;
use App\Enums\ServiceMode;
use App\Enums\StockMode;
use App\Enums\StockMovementType;
use App\Enums\SubscriptionState;
use App\Models\Business;
use App\Models\Category;
use App\Models\Plan;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\Review;
use App\Models\User;
use App\Models\Zone;
use App\Support\ProhibitedItems;
use Illuminate\Database\Seeder;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Negocios y clientes de muestra con fotos, videos e historias, para ver cómo
 * se ve Silvestre con vida. SOLO para desarrollo local.
 *
 * Fotos: Wikimedia Commons (CC0, dominio público o CC BY-SA); los créditos
 * quedan en storage/app/public/demo/CREDITOS.txt. Video del vivero: clip CC0
 * de MDN. Los demás videos se generan con ffmpeg a partir de las fotos. Nada
 * de esto debe publicarse en producción.
 *
 * Se puede correr varias veces: los archivos ya descargados no se vuelven a
 * bajar.
 *
 * Todas las cuentas usan la contraseña: secret123
 */
class ShowcaseSeeder extends Seeder
{
    private const PASSWORD = 'secret123';

    private const FLOWER_VIDEO = 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4';

    /** @var array<string, string> ruta en disco => URL */
    private array $queue = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $specs = $this->specs();

        $this->command?->info('Descargando fotos de muestra (solo la primera vez)…');
        foreach ($specs as $spec) {
            $this->queueImages($spec);
        }
        $this->queue['demo/shared/flower.mp4'] = self::FLOWER_VIDEO;
        $this->downloadQueue();

        $this->command?->info('Generando videos con ffmpeg…');
        foreach ($specs as $spec) {
            $this->makeVideos($spec);
        }

        $customers = $this->customers();

        foreach ($specs as $i => $spec) {
            $this->command?->info("  · {$spec['name']}");
            $this->createBusiness($spec, $customers, $i);
        }

        $this->follows($customers);
    }

    // ================================================================ cuentas

    /** @return array<int, User> */
    private function customers(): array
    {
        $people = [
            ['ana@test.gt', 'Ana Lucía Pérez'],
            ['carlos@test.gt', 'Carlos Méndez'],
            ['maria@test.gt', 'María José Castillo'],
            ['jorge@test.gt', 'Jorge Us'],
            ['lucia@test.gt', 'Lucía Morales'],
            ['diego@test.gt', 'Diego Pérez'],
            ['sofia@test.gt', 'Sofía Gómez'],
        ];

        return collect($people)->map(function (array $p) {
            $user = User::updateOrCreate(['email' => $p[0]], [
                'name' => $p[1],
                'password' => self::PASSWORD,
                'account_type' => AccountType::Personal,
            ]);
            // Verificadas y con más de 48 h, para que sus reseñas sean válidas.
            $user->forceFill([
                'email_verified_at' => now()->subMonths(2),
                'created_at' => now()->subMonths(3),
            ])->save();

            return $user;
        })->all();
    }

    private function follows(array $customers): void
    {
        $slugs = fn (array $list) => Business::whereIn('slug', $list)->pluck('id');

        // Ana sigue a varios, pero NO a los Pro café, barbería y uñas: así ve
        // su publicidad en el Inicio.
        $plan = [
            'ana@test.gt' => ['galletasdechela', 'viverolasorquideas', 'tamalesdonarosa', 'tejidosixchel'],
            'carlos@test.gt' => ['cafelacumbre', 'barberiaelpatron', 'plomeriaramirez'],
            'maria@test.gt' => ['unasporkari', 'galletasdechela', 'viverolasorquideas'],
            'jorge@test.gt' => ['barberiaelpatron', 'tamalesdonarosa'],
            'lucia@test.gt' => ['unasporkari', 'tejidosixchel', 'cafelacumbre'],
            'diego@test.gt' => ['cafelacumbre', 'galletasdechela'],
            'sofia@test.gt' => ['viverolasorquideas', 'unasporkari', 'tejidosixchel'],
        ];

        foreach ($customers as $customer) {
            $customer->follows()->sync($slugs($plan[$customer->email] ?? []));
        }

        // El conteo de seguidores sale de los seguimientos reales.
        Business::query()->each(fn (Business $b) => $b->forceFill([
            'followers_count' => $b->followers()->count(),
        ])->save());
    }

    // =========================================================== media: fotos

    private function img(string $slug, string $name): string
    {
        return "demo/{$slug}/{$name}.jpg";
    }

    /** Wikimedia pide identificarse con un User-Agent descriptivo. */
    private const USER_AGENT = 'SilvestreDemoSeeder/1.0 (datos de prueba en desarrollo local)';

    /** @var array<string, array{title: string, license: string, artist: string, source: string}> */
    private array $credits = [];

    /**
     * Busca fotos con licencia libre en Wikimedia Commons sobre un tema.
     * Solo fotos (no diagramas ni SVG) y de al menos 700 px de ancho.
     *
     * @return array<int, array{url: string, title: string, license: string, artist: string, source: string}>
     */
    private function commonsPhotos(string $query, int $count): array
    {
        // Caché en disco: correr el seeder otra vez no vuelve a consultar la API.
        $cacheFile = 'demo-commons-cache.json';
        $cache = json_decode((string) Storage::disk('local')->get($cacheFile), true) ?: [];

        if (! isset($cache[$query])) {
            $cache[$query] = $this->queryCommons($query);
            Storage::disk('local')->put($cacheFile, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return collect($cache[$query])
            ->sortBy('index')
            ->map(function (array $page) {
                $info = $page['imageinfo'][0] ?? [];

                return [
                    'url' => $info['thumburl'] ?? null,
                    'mime' => $info['mime'] ?? '',
                    'width' => $info['width'] ?? 0,
                    'title' => $page['title'] ?? '',
                    'license' => $info['extmetadata']['LicenseShortName']['value'] ?? '',
                    'artist' => trim(strip_tags($info['extmetadata']['Artist']['value'] ?? '')),
                    'source' => $info['descriptionurl'] ?? '',
                ];
            })
            ->filter(fn (array $p) => $p['url'] && in_array($p['mime'], ['image/jpeg', 'image/png'], true) && $p['width'] >= 700)
            ->take($count)
            ->values()
            ->all();
    }

    /**
     * Una búsqueda a la API de Commons, respetando sus límites: una petición a
     * la vez, pausa entre búsquedas y espera cuando responde 429.
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function queryCommons(string $query): array
    {
        foreach (range(1, 6) as $attempt) {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(40)
                ->get('https://commons.wikimedia.org/w/api.php', [
                    'action' => 'query', 'format' => 'json', 'maxlag' => 5,
                    'generator' => 'search', 'gsrsearch' => "filetype:bitmap {$query}",
                    'gsrnamespace' => 6, 'gsrlimit' => 50,
                    'prop' => 'imageinfo', 'iiprop' => 'url|size|mime|extmetadata',
                    'iiextmetadatafilter' => 'LicenseShortName|Artist', 'iiurlwidth' => 1000,
                ]);

            if ($response->successful() && ! $response->json('error')) {
                sleep(2);

                return $response->json('query.pages', []);
            }

            $wait = (int) ($response->header('Retry-After') ?: 10 * $attempt);
            $this->command?->warn("    Commons pidió esperar ({$response->status()}); reintento en {$wait} s…");
            sleep(min($wait, 60));
        }

        return [];
    }

    private function queueImages(array $spec): void
    {
        $slug = $spec['slug'];
        $items = count($spec['products'] ?? []) + count($spec['services'] ?? []);

        $photos = $this->commonsPhotos($spec['query'], 1 + $items + 8);
        $covers = $this->commonsPhotos($spec['coverQuery'], 1);

        if ($photos === []) {
            $this->command?->warn("    Commons no devolvió fotos para «{$spec['query']}»");

            return;
        }

        // Si hay menos fotos de las necesarias, se reutilizan en ciclo.
        $pick = fn (int $i) => $photos[$i % count($photos)];
        $assign = function (string $name, array $photo) use ($slug) {
            $path = $this->img($slug, $name);
            $this->queue[$path] = $photo['url'];
            $this->credits[$path] = collect($photo)->only(['title', 'license', 'artist', 'source'])->all();
        };

        $assign('avatar', $pick(0));
        $assign('cover', $covers[0] ?? $pick(1));

        foreach (range(1, max($items, 1)) as $n) {
            $assign("item-{$n}", $pick($n));
        }

        foreach (range(1, 8) as $n) {
            $assign("gallery-{$n}", $pick($items + $n));
        }
    }

    private function downloadQueue(): void
    {
        $disk = Storage::disk('public');
        $pending = collect($this->queue)->reject(fn ($url, $path) => $disk->exists($path));

        // De 3 en 3 y con reintentos: lo que falle en una pasada se vuelve a intentar.
        foreach (range(1, 3) as $attempt) {
            $pending = $pending->reject(fn ($url, $path) => $disk->exists($path));

            if ($pending->isEmpty()) {
                break;
            }

            foreach ($pending->chunk(2, preserveKeys: true) as $chunk) {
                $responses = Http::pool(fn (Pool $pool) => $chunk->map(
                    fn (string $url, string $path) => $pool->as($path)
                        ->withHeaders(['User-Agent' => self::USER_AGENT])
                        ->timeout(60)->get($url)
                )->all());

                $throttled = false;
                foreach ($chunk->keys() as $path) {
                    $response = $responses[$path] ?? null;

                    if ($response instanceof Response && $response->successful()) {
                        $disk->put($path, $response->body());
                    } elseif ($response instanceof Response && $response->status() === 429) {
                        $throttled = true;
                    }
                }

                // Si el servidor de imágenes pide calma, se la damos.
                $throttled ? sleep(15) : usleep(600_000);
            }
        }

        // Algunas miniaturas de Commons no se pueden generar. En vez de dejar
        // una imagen rota, se reutiliza otra foto del mismo negocio.
        $pending->reject(fn ($url, $path) => $disk->exists($path))->each(function ($url, string $path) use ($disk) {
            $sibling = collect($disk->files(dirname($path)))->first(fn (string $f) => str_ends_with($f, '.jpg'));

            if ($sibling) {
                $disk->copy($sibling, $path);
                $this->credits[$path] = $this->credits[$sibling] ?? $this->credits[$path] ?? [];
            } else {
                $this->command?->warn("    No se pudo descargar {$path}");
            }
        });

        $this->writeCredits();
    }

    /** Las licencias CC BY-SA exigen dar crédito: queda todo en un archivo. */
    private function writeCredits(): void
    {
        $lines = collect($this->credits)->map(fn (array $c, string $path) => sprintf(
            "%s\n  Foto: %s\n  Autor: %s\n  Licencia: %s\n  Fuente: %s\n",
            $path, $c['title'], $c['artist'] ?: 'Desconocido', $c['license'] ?: 'Ver fuente', $c['source'],
        ));

        Storage::disk('public')->put('demo/CREDITOS.txt', implode("\n", [
            'Fotos de prueba de Wikimedia Commons, SOLO para desarrollo local.',
            'Video del vivero: flower.mp4, CC0, MDN Web Docs.',
            'Los demás videos se generaron con ffmpeg a partir de estas fotos.',
            '',
            ...$lines,
        ]));
    }

    // ========================================================== media: videos

    /**
     * Genera dos videos por negocio con un paneo lento sobre sus fotos:
     * uno vertical para historias y uno cuadrado para publicaciones.
     */
    private function makeVideos(array $spec): void
    {
        $disk = Storage::disk('public');
        $slug = $spec['slug'];

        if ($spec['flower'] ?? false) {
            foreach (['story-video.mp4', 'post-video.mp4'] as $name) {
                if (! $disk->exists("demo/{$slug}/{$name}") && $disk->exists('demo/shared/flower.mp4')) {
                    $disk->copy('demo/shared/flower.mp4', "demo/{$slug}/{$name}");
                }
            }
            $this->ffmpeg(['-y', '-ss', '1', '-i', $disk->path('demo/shared/flower.mp4'), '-frames:v', '1', '-q:v', '3', $disk->path("demo/{$slug}/flower-poster.jpg")], "demo/{$slug}/flower-poster.jpg");

            return;
        }

        $this->kenBurns($this->img($slug, 'gallery-1'), "demo/{$slug}/story-video.mp4", 540, 960);
        $this->kenBurns($this->img($slug, 'gallery-2'), "demo/{$slug}/post-video.mp4", 640, 640);
    }

    private function kenBurns(string $source, string $target, int $w, int $h): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists($target) || ! $disk->exists($source)) {
            return;
        }

        $filter = sprintf(
            "scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d,zoompan=z='min(zoom+0.0012,1.18)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=144:s=%dx%d:fps=24,format=yuv420p",
            $w * 2, $h * 2, $w * 2, $h * 2, $w, $h
        );

        $this->ffmpeg([
            '-y', '-loop', '1', '-i', $disk->path($source),
            '-vf', $filter, '-t', '6', '-c:v', 'libx264', '-preset', 'veryfast',
            '-crf', '28', '-movflags', '+faststart', '-an', $disk->path($target),
        ], $target);
    }

    private function ffmpeg(array $args, string $target): void
    {
        if (Storage::disk('public')->exists($target) && ! str_ends_with($target, '.mp4')) {
            return;
        }

        @mkdir(dirname(Storage::disk('public')->path($target)), 0775, true);
        $result = Process::timeout(180)->run(['ffmpeg', '-loglevel', 'error', ...$args]);

        if ($result->failed()) {
            $this->command?->warn("    ffmpeg falló para {$target}");
        }
    }

    private function videoOrImage(string $slug, string $video, string $poster): array
    {
        $disk = Storage::disk('public');

        return $disk->exists("demo/{$slug}/{$video}")
            ? ['video', "demo/{$slug}/{$video}", $poster]
            : ['image', $poster, null];
    }

    // =============================================================== negocios

    private function createBusiness(array $spec, array $customers, int $index): void
    {
        $slug = $spec['slug'];

        $owner = User::updateOrCreate(['email' => $spec['owner'][1]], [
            'name' => $spec['owner'][0],
            'password' => self::PASSWORD,
            'account_type' => AccountType::Business,
        ]);
        $owner->forceFill(['email_verified_at' => now()->subMonths(4), 'created_at' => now()->subMonths(5)])->save();

        // Se recrea desde cero para que el seeder sea repetible.
        Business::withTrashed()->where('slug', $slug)->get()->each->forceDelete();

        $business = $owner->ownedBusinesses()->create([
            'name' => $spec['name'],
            'slug' => $slug,
            'kind' => $spec['kind'] ?? BusinessKind::Negocio,
            'offering' => $spec['offering'],
            'intro' => $spec['intro'],
            'about' => $spec['about'],
            'category_id' => Category::where('slug', $spec['category'])->value('id'),
            'zone_id' => Zone::where('slug', $spec['zone'])->value('id'),
            'city' => config('silvestre.city'),
            'address' => $spec['address'] ?? null,
            'whatsapp' => $spec['whatsapp'],
            'avatar_path' => $this->img($slug, 'avatar'),
            'cover_path' => $this->img($slug, 'cover'),
        ]);

        $business->forceFill([
            'is_verified' => $spec['verified'] ?? false,
            'onboarding_completed_at' => now()->subMonths(2),
            'policy_version' => ProhibitedItems::POLICY_VERSION,
            'policy_accepted_at' => now()->subMonths(2),
            'activation_score' => 100,
            'published_at' => now()->subMonths(2),
            'activated_at' => now()->subMonths(2),
            'created_at' => now()->subMonths(2),
        ])->save();

        $business->members()->attach($owner->id, ['role' => BusinessRole::Owner->value, 'accepted_at' => now()]);
        $owner->forceFill(['active_business_id' => $business->id])->save();

        foreach ($spec['hours'] as [$days, $open, $close]) {
            foreach ($days as $day) {
                $business->hours()->create(['weekday' => $day, 'opens_at' => $open, 'closes_at' => $close]);
            }
        }

        foreach ($spec['fulfillment'] ?? [] as $method) {
            $business->fulfillment()->create(['method' => $method]);
        }
        foreach ($spec['modes'] ?? [] as $mode) {
            $business->serviceModes()->create(['mode' => $mode, 'travel_fee_cents' => $mode === ServiceMode::AtCustomer ? 2500 : 0]);
        }
        foreach ($spec['zones'] ?? [] as [$zoneSlug, $fee]) {
            $zone = Zone::where('slug', $zoneSlug)->first();
            $business->deliveryZones()->create(['zone_id' => $zone?->id, 'name' => $zone?->name ?? $zoneSlug, 'fee_cents' => $fee * 100]);
        }
        foreach ($spec['payments'] as $method) {
            $business->paymentMethods()->create([
                'method' => $method,
                'details' => $method === PaymentMethod::BankTransfer ? [
                    'bank' => 'Banco Industrial', 'account_type' => 'Monetaria',
                    'account_number' => '000-000000-0', 'holder' => $spec['owner'][0],
                ] : null,
            ]);
        }

        $items = $this->catalog($business, $spec);
        $this->subscription($business, $spec['plan']);
        $this->posts($business, $spec, $customers);
        $this->stories($business, $spec);
        $this->reviews($business, $spec, $customers, $items);
    }

    /** @return array<int, array{name: string, price: int, variant: ?int, service: ?int}> */
    private function catalog(Business $business, array $spec): array
    {
        $slug = $spec['slug'];
        $items = [];
        $n = 0;

        foreach ($spec['products'] ?? [] as $i => $p) {
            $n++;
            $product = $business->products()->create([
                'name' => $p['name'], 'slug' => str($p['name'])->slug(), 'description' => $p['desc'],
                'stock_mode' => $p['mode'], 'price_mode' => PriceMode::Fixed,
                'capacity_per_day' => $p['capacity'] ?? null, 'lead_time_days' => $p['lead'] ?? null,
                'position' => $i,
            ]);
            $product->media()->create(['path' => $this->img($slug, "item-{$n}")]);

            $variant = $product->variants()->create([
                'name' => $p['variant'] ?? 'Única', 'price_cents' => $p['price'] * 100,
                'cost_cents' => isset($p['cost']) ? $p['cost'] * 100 : null,
                'low_stock_threshold' => $p['low'] ?? 0,
            ]);

            if ($p['mode'] === StockMode::Inventory) {
                $variant->movements()->create(['type' => StockMovementType::Entry, 'qty' => $p['stock'], 'reason' => 'Inventario inicial']);
                $variant->syncStockCache();
            }

            $items[] = ['name' => $p['name'], 'price' => $p['price'] * 100, 'variant' => $variant->id, 'service' => null];
        }

        foreach ($spec['services'] ?? [] as $i => $s) {
            $n++;
            $service = $business->services()->create([
                'name' => $s['name'], 'slug' => str($s['name'])->slug(), 'description' => $s['desc'],
                'price_mode' => $s['price_mode'] ?? PriceMode::Fixed,
                'price_cents' => isset($s['price']) ? $s['price'] * 100 : null,
                'duration_min' => $s['duration'], 'position' => $i,
            ]);
            $service->media()->create(['path' => $this->img($slug, "item-{$n}")]);

            $items[] = ['name' => $s['name'], 'price' => ($s['price'] ?? 0) * 100, 'variant' => null, 'service' => $service->id];
        }

        return $items;
    }

    private function subscription(Business $business, string $plan): void
    {
        $pro = Plan::byCode(PlanCode::Pro)->id;

        $business->subscription()->create(match ($plan) {
            'trial' => ['plan_id' => $pro, 'state' => SubscriptionState::Trial, 'trial_ends_at' => now()->addDays(58)],
            'active' => ['plan_id' => $pro, 'state' => SubscriptionState::Active, 'current_period_ends_at' => now()->addDays(21)],
            // Ya tuvo su prueba y no pagó: sigue operando en Gratis.
            default => ['plan_id' => $pro, 'state' => SubscriptionState::Expired, 'trial_ends_at' => now()->subDays(12), 'ended_at' => now()->subDays(12)],
        });
    }

    private function posts(Business $business, array $spec, array $customers): void
    {
        $slug = $spec['slug'];
        $gallery = 3; // gallery-1 y gallery-2 se usan para los videos
        $last = null;

        foreach ($spec['posts'] as [$title, $body, $images, $withVideo, $hoursAgo]) {
            $at = now()->subHours($hoursAgo);
            $post = $business->posts()->create([
                'title' => $title, 'body' => $body,
                'week_key' => $at->format('o-\WW'),
                'published_at' => $at,
                'created_at' => $at,
            ]);

            $this->engagement($business, $post, $customers, $at);

            $position = 0;
            if ($withVideo) {
                $poster = ($spec['flower'] ?? false) ? "demo/{$slug}/flower-poster.jpg" : $this->img($slug, 'gallery-2');
                [$type, $path, $posterPath] = $this->videoOrImage($slug, 'post-video.mp4', $poster);
                $post->media()->create(['type' => $type, 'path' => $path, 'poster_path' => $posterPath, 'position' => $position++]);
            }

            foreach (range(1, $images) as $_) {
                $post->media()->create(['type' => 'image', 'path' => $this->img($slug, 'gallery-'.(($gallery - 1) % 6 + 3)), 'position' => $position++]);
                $gallery++;
            }

            $last = max($last ?? $at, $at);
        }

        $business->forceFill(['last_posted_at' => $last])->save();
    }

    /**
     * Reacciones y comentarios REALES (filas de verdad), para que los conteos
     * cuadren con lo que se ve al abrir la publicación.
     */
    private function engagement(Business $business, Post $post, array $customers, \DateTimeInterface $at): void
    {
        $questions = [
            '¡Se ven riquísimas! ¿Todavía tienen disponible?',
            '¿Hacen envíos a la zona 11?',
            'Ya pedí las mías, las recomiendo mucho.',
            '¿Aceptan transferencia?',
            'Qué bonito trabajo, se nota el cariño.',
            '¿Cuánto tiempo antes hay que pedir?',
            'Me encantó la última vez que compré.',
        ];
        $answers = [
            '¡Gracias! Sí hay, escríbeme por WhatsApp y te aparto.',
            'Sí llegamos, el envío está en la pestaña Info del perfil.',
            '¡Gracias por su apoyo! 💚',
            'Sí, con transferencia o en efectivo al recibir.',
        ];

        $people = collect($customers)->shuffle();

        foreach ($people->take(random_int(2, count($customers))) as $person) {
            PostReaction::create(['post_id' => $post->id, 'user_id' => $person->id, 'created_at' => $at]);
        }

        $total = 0;
        foreach ($people->take(random_int(0, 3)) as $i => $person) {
            $when = Carbon::instance($at)->addMinutes(20 + $i * 35);
            $comment = $post->comments()->create([
                'user_id' => $person->id,
                'body' => $questions[array_rand($questions)],
                'created_at' => $when,
            ]);
            $total++;

            // El negocio responde a la mayoría, como pasaría de verdad.
            if (random_int(0, 3) > 0) {
                $post->comments()->create([
                    'user_id' => $business->owner_id,
                    'parent_id' => $comment->id,
                    'as_business_id' => $business->id,
                    'body' => $answers[array_rand($answers)],
                    'created_at' => $when->copy()->addMinutes(12),
                ]);
                $total++;
            }
        }

        $post->forceFill([
            'reactions_count' => $post->reactions()->count(),
            'comments_count' => $total,
        ])->save();
    }

    private function stories(Business $business, array $spec): void
    {
        $slug = $spec['slug'];
        $poster = ($spec['flower'] ?? false) ? "demo/{$slug}/flower-poster.jpg" : $this->img($slug, 'gallery-1');

        // Historias vivas: salen en la fila del Inicio y en el anillo del perfil.
        foreach ($spec['stories'] as $i => [$caption, $isVideo]) {
            $at = now()->subHours(1 + $i * 3);
            [$type, $path, $posterPath] = $isVideo
                ? $this->videoOrImage($slug, 'story-video.mp4', $poster)
                : ['image', $this->img($slug, 'gallery-'.(6 + $i % 3)), null];

            $business->stories()->create([
                'media_path' => $path, 'media_type' => $type, 'poster_path' => $posterPath,
                'caption' => $caption, 'expires_at' => $at->addHours(24), 'created_at' => $at,
            ]);
        }

        // Destacadas: historias viejas que el negocio dejó fijas en su perfil.
        foreach ($spec['highlights'] as $h => [$title, $captions]) {
            $highlight = $business->highlights()->create(['title' => $title, 'position' => $h]);

            foreach ($captions as $i => $caption) {
                $at = now()->subDays(12 + $h * 7 + $i);
                $story = $business->stories()->create([
                    'media_path' => $this->img($slug, 'gallery-'.(3 + ($h * 2 + $i) % 6)),
                    'media_type' => 'image', 'caption' => $caption,
                    'expires_at' => $at->addHours(24), 'created_at' => $at,
                ]);
                $highlight->stories()->attach($story->id, ['position' => $i]);
            }
        }
    }

    private function reviews(Business $business, array $spec, array $customers, array $items): void
    {
        foreach ($spec['reviews'] as $i => $review) {
            [$stars, $body] = $review;
            $reply = $review[2] ?? null;
            $customer = $customers[$i % count($customers)];
            $item = $items[$i % max(count($items), 1)];
            $at = now()->subDays(3 + $i * 5);

            // Cada reseña nace de un pedido realmente entregado: así funciona
            // la regla de Silvestre y así se ve la insignia "compra verificada".
            $order = $business->orders()->create([
                'user_id' => $customer->id,
                'type' => $item['service'] ? OrderType::Service : OrderType::Product,
                'state' => OrderState::Delivered,
                'customer_name' => $customer->name,
                'customer_phone' => '5'.str_pad((string) random_int(0, 9999999), 7, '0'),
                'payment_method' => $spec['payments'][0],
                'subtotal_cents' => $item['price'],
                'total_cents' => $item['price'],
                'currency' => 'GTQ',
                'placed_at' => $at->subDays(2),
                'completed_at' => $at->subDay(),
                'created_at' => $at->subDays(2),
            ]);
            $order->items()->create([
                'product_variant_id' => $item['variant'], 'service_id' => $item['service'],
                'name' => $item['name'], 'qty' => 1, 'unit_price_cents' => $item['price'],
            ]);

            $created = Review::create([
                'business_id' => $business->id, 'user_id' => $customer->id, 'order_id' => $order->id,
                'stars' => $stars, 'body' => $body, 'created_at' => $at,
            ]);

            if ($reply) {
                $created->reply()->create(['user_id' => $business->owner_id, 'body' => $reply, 'created_at' => $at->addHours(5)]);
            }
        }
    }

    // ======================================================== el contenido

    private function specs(): array
    {
        $weekdays = [1, 2, 3, 4, 5];
        $cashAndTransfer = [PaymentMethod::CashOnDelivery, PaymentMethod::BankTransfer];

        return [
            [
                'slug' => 'galletasdechela', 'name' => 'Galletas de la Abuela Chela', 'query' => 'chocolate chip cookies', 'coverQuery' => 'cookies bakery',
                'owner' => ['Chela Ramírez', 'chela@test.mx'], 'plan' => 'trial', 'verified' => true,
                'category' => 'reposteria-y-panaderia', 'offering' => 'productos', 'zone' => 'zona-10',
                'address' => '12 calle 3-45, zona 10', 'whatsapp' => '55551234',
                'intro' => 'Galletas horneadas en casa, en tandas pequeñas, cada jueves.',
                'about' => 'Empecé horneando las recetas de mi abuela para la familia. Hoy entregamos en la zona 10, 14 y 15. Todo lleva mantequilla de verdad y nada de conservantes.',
                'hours' => [[$weekdays, '08:00', '18:00'], [[6], '09:00', '13:00']],
                'fulfillment' => [FulfillmentMethod::Pickup, FulfillmentMethod::LocalDelivery],
                'zones' => [['zona-10', 20], ['zona-14', 25], ['zona-15', 30]],
                'payments' => $cashAndTransfer,
                'products' => [
                    ['name' => 'Galletas de chispas de chocolate', 'desc' => 'Crujientes por fuera, suaves por dentro.', 'variant' => 'Docena', 'price' => 65, 'cost' => 42, 'mode' => StockMode::Inventory, 'stock' => 24, 'low' => 5],
                    ['name' => 'Polvorones de nuez', 'desc' => 'Se deshacen en la boca. Bolsa de 15 piezas.', 'price' => 45, 'cost' => 28, 'mode' => StockMode::Inventory, 'stock' => 15, 'low' => 4],
                    ['name' => 'Caja regalo surtida', 'desc' => '24 piezas de cinco sabores, con tarjeta.', 'price' => 180, 'cost' => 110, 'mode' => StockMode::Inventory, 'stock' => 3, 'low' => 5],
                    ['name' => 'Galletas decoradas a mano', 'desc' => 'Con tu diseño. Pídelas con 2 días de anticipación.', 'variant' => 'Por pieza', 'price' => 12, 'mode' => StockMode::MadeToOrder, 'capacity' => 60, 'lead' => 2],
                ],
                'posts' => [
                    ['Horneada del jueves: avena y pasas', "Esta semana volvieron las de avena. Salieron 6 docenas y ya van apartadas 4.\n\nSi quieres, escríbeme hoy antes de las 6 y te las llevo mañana.", 3, false, 5],
                    ['Así empacamos tus pedidos', 'Cada caja va sellada y con papel encerado para que lleguen crujientes. Nada de galletas quebradas en el camino.', 1, true, 30],
                    ['Caja especial para el Día del Cariño', 'Corazones de mantequilla con glaseado rosa. Solo 40 cajas.', 2, false, 140],
                ],
                'stories' => [['Saliendo del horno', false], ['Ruta de entregas de hoy: zona 10, 14 y 15', true], ['Quedan 3 cajas regalo', false]],
                'highlights' => [['Cómo trabajo', ['La masa reposa toda la noche', 'Horneamos en tandas de 12']], ['Clientes', ['Pedido para una boda', 'Cumpleaños de 80 galletas']]],
                'reviews' => [
                    [5, 'Pedí la caja surtida para el cumpleaños de mi mamá y llegó puntual y bien empacada.', '¡Gracias, Ana! Los polvorones siempre se van primero.'],
                    [5, 'Las de chispas son adictivas. Ya voy por mi tercera docena.'],
                    [4, 'Muy ricas. Llegaron 20 minutos tarde, por eso no le doy 5.', 'Tienes razón, ese día se nos complicó la ruta. La próxima va con envío gratis.'],
                    [5, 'Las decoradas quedaron idénticas a la referencia que mandé.'],
                    [5, 'Buen precio para la calidad. Se nota que son caseras.'],
                    [5, 'Las mejores galletas de la zona 10, sin exagerar.'],
                ],
            ],
            [
                'slug' => 'cafelacumbre', 'name' => 'Café La Cumbre', 'query' => 'roasted coffee beans', 'coverQuery' => 'coffee plantation Guatemala',
                'owner' => ['Andrés Barrios', 'andres@test.gt'], 'plan' => 'active', 'verified' => true,
                'category' => 'tienda-y-abarrotes', 'offering' => 'productos', 'zone' => 'zona-4',
                'address' => 'Vía 5, 4 Grados Norte, zona 4', 'whatsapp' => '55552345',
                'intro' => 'Café de Huehuetenango y Antigua, tostado cada lunes en la zona 4.',
                'about' => 'Compramos directo a fincas pequeñas y tostamos en lotes de 5 kilos. Te decimos la finca, la altura y la fecha de tueste de cada bolsa.',
                'hours' => [[[1, 2, 3, 4, 5, 6], '07:00', '19:00']],
                'fulfillment' => [FulfillmentMethod::Pickup, FulfillmentMethod::LocalDelivery, FulfillmentMethod::Shipping],
                'zones' => [['zona-4', 15], ['zona-9', 20], ['zona-10', 20]],
                'payments' => [PaymentMethod::CashOnPickup, PaymentMethod::BankTransfer, PaymentMethod::CardOnDelivery],
                'products' => [
                    ['name' => 'Café de Huehuetenango molido', 'desc' => 'Notas a chocolate y panela. Tueste medio.', 'variant' => '1 libra', 'price' => 85, 'cost' => 52, 'mode' => StockMode::Inventory, 'stock' => 40, 'low' => 8],
                    ['name' => 'Café en grano de Antigua', 'desc' => 'Acidez brillante, final a cítricos.', 'variant' => '1 libra', 'price' => 95, 'cost' => 60, 'mode' => StockMode::Inventory, 'stock' => 30, 'low' => 6],
                    ['name' => 'Cold brew', 'desc' => 'Extraído 18 horas en frío. Pídelo un día antes.', 'variant' => '1 litro', 'price' => 60, 'mode' => StockMode::MadeToOrder, 'capacity' => 20, 'lead' => 1],
                ],
                'posts' => [
                    ['Tostamos el lunes, entregamos el martes', 'Así se ve el lote de esta semana de Huehuetenango. Si pides hoy, te llega recién tostado.', 2, true, 3],
                    ['Método V60 en 4 pasos', "1. 15 g de café molido medio fino.\n2. 250 ml de agua a 93 °C.\n3. Pre-infusión de 30 segundos.\n4. Vierte en círculos hasta los 2:30.", 3, false, 50],
                ],
                'stories' => [['Lote nuevo de Huehue', true], ['Hoy abrimos hasta las 7', false]],
                'highlights' => [['Fincas', ['Finca en La Libertad, Huehue', 'Secado al sol']], ['Métodos', ['Prensa francesa', 'Chemex']]],
                'reviews' => [
                    [5, 'El de Huehue es increíble. Por fin un café con fecha de tueste.'],
                    [5, 'Me explicaron cómo prepararlo en prensa francesa. Atención de 10.', 'Gracias, Carlos. Cualquier duda con el molido, nos escribes.'],
                    [4, 'Muy buen café, un poco caro para diario.'],
                    [5, 'El cold brew salva mis tardes.'],
                    [5, 'Llegó en 40 minutos a la zona 9.'],
                    [5, 'Lo regalé en Navidad y todos preguntaron dónde lo compré.'],
                ],
            ],
            [
                'slug' => 'viverolasorquideas', 'name' => 'Vivero Las Orquídeas', 'query' => 'Phalaenopsis orchid', 'coverQuery' => 'orchid nursery greenhouse', 'flower' => true,
                'owner' => ['Rosa Pérez', 'rosa@test.gt'], 'plan' => 'free',
                'category' => 'plantas-y-jardin', 'offering' => 'productos', 'zone' => 'mixco',
                'address' => 'Calzada San Juan 10-20, Mixco', 'whatsapp' => '55553456',
                'intro' => 'Orquídeas, suculentas y macetas de barro pintadas a mano.',
                'about' => 'Somos un vivero familiar en Mixco. Te enseñamos a cuidar cada planta para que te dure años.',
                'hours' => [[[2, 3, 4, 5, 6, 0], '08:00', '17:00']],
                'fulfillment' => [FulfillmentMethod::Pickup, FulfillmentMethod::LocalDelivery],
                'zones' => [['mixco', 20], ['zona-11', 30]],
                'payments' => [PaymentMethod::CashOnDelivery, PaymentMethod::CashOnPickup],
                'products' => [
                    ['name' => 'Orquídea Phalaenopsis', 'desc' => 'En flor, con maceta de plástico transparente.', 'price' => 150, 'cost' => 90, 'mode' => StockMode::Inventory, 'stock' => 12, 'low' => 3],
                    ['name' => 'Suculentas surtidas', 'desc' => 'Tres suculentas en maceta pequeña.', 'variant' => 'Juego de 3', 'price' => 60, 'cost' => 30, 'mode' => StockMode::Inventory, 'stock' => 25, 'low' => 5],
                    ['name' => 'Maceta de barro pintada', 'desc' => 'Pintada a mano en San Juan Sacatepéquez.', 'price' => 45, 'cost' => 22, 'mode' => StockMode::Inventory, 'stock' => 8, 'low' => 3],
                ],
                'posts' => [
                    ['Así florece una orquídea', 'Paciencia y luz indirecta. Así se abrió esta en el vivero.', 1, true, 8],
                    ['Llegaron suculentas nuevas', 'Más de 20 variedades. Ven el sábado y te ayudamos a armar tu jardín.', 3, false, 70],
                ],
                'stories' => [['Floreciendo en el vivero', true], ['Sábado de ofertas', false]],
                'highlights' => [['Cuidados', ['Riego: una vez por semana', 'Luz indirecta']]],
                'reviews' => [
                    [5, 'Mi orquídea lleva 8 meses en flor. Me explicaron todo.'],
                    [5, 'Las macetas pintadas están preciosas.'],
                    [4, 'Buenas plantas. Tardaron un día más en la entrega.'],
                    [5, 'Atención muy amable, se nota que saben.'],
                    [5, 'Las suculentas llegaron perfectas.'],
                    [4, 'Precios justos. Me hubiera gustado más variedad de orquídeas.'],
                ],
            ],
            [
                'slug' => 'barberiaelpatron', 'name' => 'Barbería El Patrón', 'query' => 'barbershop haircut', 'coverQuery' => 'barber shop interior',
                'owner' => ['Kevin Ajú', 'kevin@test.gt'], 'plan' => 'trial', 'verified' => true,
                'category' => 'belleza-y-cuidado-personal', 'offering' => 'servicios', 'zone' => 'zona-1',
                'address' => '6a avenida 12-34, zona 1', 'whatsapp' => '55554567',
                'intro' => 'Cortes clásicos y barba con toalla caliente, en el centro histórico.',
                'about' => 'Tres barberos, cero prisas. Agenda tu cita y no hagas fila.',
                'hours' => [[[2, 3, 4, 5, 6], '09:00', '20:00'], [[0], '09:00', '14:00']],
                'modes' => [ServiceMode::AtBusiness],
                'payments' => [PaymentMethod::CashOnPickup, PaymentMethod::CardOnDelivery],
                'services' => [
                    ['name' => 'Corte clásico', 'desc' => 'Tijera y máquina, con lavado.', 'price' => 50, 'duration' => 30],
                    ['name' => 'Corte y barba', 'desc' => 'Incluye perfilado y toalla caliente.', 'price' => 80, 'duration' => 45],
                    ['name' => 'Afeitado tradicional', 'desc' => 'Navaja, toalla caliente y bálsamo.', 'price' => 60, 'duration' => 30],
                ],
                'posts' => [
                    ['Fade de la semana', 'Degradado bajo con textura arriba. ¿Te animas?', 2, true, 2],
                    ['Toalla caliente, como antes', 'El afeitado tradicional vuelve a la agenda de los sábados.', 1, false, 26],
                    ['Nuevo sillón, mismo trato', 'Remodelamos para atenderte mejor.', 3, false, 120],
                ],
                'stories' => [['Agenda de hoy casi llena', false], ['Así queda un corte y barba', true], ['Último espacio a las 6 pm', false]],
                'highlights' => [['Cortes', ['Fade bajo', 'Pompadour']], ['El local', ['La entrada', 'Los sillones']]],
                'reviews' => [
                    [5, 'El mejor fade que me han hecho. Puntual con la cita.'],
                    [5, 'La toalla caliente es otro nivel.', 'Gracias, hermano. Aquí te esperamos.'],
                    [5, 'Buen ambiente y buena plática.'],
                    [4, 'Excelente corte, pero tuve que esperar 10 minutos.'],
                    [5, 'Llevé a mi hijo y quedó feliz.'],
                    [5, 'Precio justo en el centro.'],
                    [5, 'Ya no voy a otra barbería.'],
                ],
            ],
            [
                'slug' => 'tamalesdonarosa', 'name' => 'Tamales Doña Rosa', 'query' => 'tamales', 'coverQuery' => 'Guatemalan food',
                'owner' => ['Rosa Xitumul', 'rosax@test.gt'], 'plan' => 'free',
                'category' => 'comida-casera', 'offering' => 'productos', 'zone' => 'zona-7',
                'address' => 'Colonia Kaminal Juyú, zona 7', 'whatsapp' => '55555678',
                'intro' => 'Tamales colorados, chuchitos y paches como en casa. Pedidos para el sábado.',
                'about' => 'Llevo 25 años haciendo tamales para las familias de la zona 7. Pedidos hasta el jueves.',
                'hours' => [[[4, 5, 6], '06:00', '14:00']],
                'fulfillment' => [FulfillmentMethod::Pickup, FulfillmentMethod::LocalDelivery],
                'zones' => [['zona-7', 10], ['zona-11', 15]],
                'payments' => [PaymentMethod::CashOnDelivery, PaymentMethod::CashOnPickup],
                'products' => [
                    ['name' => 'Tamal colorado', 'desc' => 'Con recado, carne de marrano y aceituna.', 'variant' => 'Unidad', 'price' => 12, 'mode' => StockMode::MadeToOrder, 'capacity' => 100, 'lead' => 1],
                    ['name' => 'Chuchitos', 'desc' => 'Con salsa y queso seco.', 'variant' => 'Docena', 'price' => 60, 'mode' => StockMode::MadeToOrder, 'capacity' => 30, 'lead' => 1],
                    ['name' => 'Paches de papa', 'desc' => 'Receta de Quetzaltenango.', 'variant' => 'Unidad', 'price' => 15, 'mode' => StockMode::MadeToOrder, 'capacity' => 60, 'lead' => 1],
                ],
                'posts' => [
                    ['Pedidos para este sábado', 'Cierro pedidos el jueves a las 6 pm. Esta semana también hay paches.', 2, false, 12],
                    ['La olla de 200 tamales', 'Así se ven los sábados en la madrugada.', 1, true, 96],
                ],
                'stories' => [['Envolviendo desde las 4 am', true], ['Últimos pedidos del jueves', false]],
                'highlights' => [['La receta', ['El recado', 'La hoja de mashán']]],
                'reviews' => [
                    [5, 'Saben como los de mi abuela.'],
                    [5, 'Pedimos 50 para un cumpleaños y todos preguntaron por ellos.', 'Gracias, María José. ¡Aquí para la próxima fiesta!'],
                    [5, 'Los chuchitos con esa salsa, riquísimos.'],
                    [4, 'Muy buenos, solo que hay que pedir con tiempo.'],
                    [5, 'Los paches son de otro mundo.'],
                    [5, 'Llegaron calientitos a la zona 11.'],
                ],
            ],
            [
                'slug' => 'unasporkari', 'name' => 'Uñas por Kari', 'query' => 'manicure nails', 'coverQuery' => 'nail salon',
                'owner' => ['Karina Solís', 'kari@test.gt'], 'plan' => 'active', 'verified' => true,
                'category' => 'belleza-y-cuidado-personal', 'offering' => 'servicios', 'zone' => 'zona-15',
                'address' => 'Vista Hermosa II, zona 15', 'whatsapp' => '55556789',
                'intro' => 'Manicure, pedicure y uñas acrílicas. Voy a tu casa o te atiendo en mi estudio.',
                'about' => 'Trabajo con material esterilizado y productos libres de tolueno. Agenda con 24 horas de anticipación.',
                'hours' => [[[1, 2, 3, 4, 5, 6], '09:00', '19:00']],
                'modes' => [ServiceMode::AtBusiness, ServiceMode::AtCustomer],
                'zones' => [['zona-15', 0], ['zona-16', 25], ['zona-10', 25]],
                'payments' => [PaymentMethod::CashOnDelivery, PaymentMethod::BankTransfer, PaymentMethod::MobileWallet],
                'services' => [
                    ['name' => 'Manicure semipermanente', 'desc' => 'Dura hasta 3 semanas.', 'price' => 120, 'duration' => 60],
                    ['name' => 'Pedicure spa', 'desc' => 'Exfoliación, masaje y esmaltado.', 'price' => 150, 'duration' => 75],
                    ['name' => 'Uñas acrílicas con diseño', 'desc' => 'El precio depende del diseño.', 'price_mode' => PriceMode::From, 'price' => 200, 'duration' => 120],
                ],
                'posts' => [
                    ['Diseño de la semana: flores secas', 'Encapsulado con flores naturales. ¡Me encantó cómo quedó!', 3, false, 6],
                    ['Así trabajo a domicilio', 'Llevo todo esterilizado y dejo tu espacio limpio.', 1, true, 44],
                    ['Colores de temporada', 'Nudes y terracotas para esta temporada.', 2, false, 150],
                ],
                'stories' => [['Diseño de hoy', false], ['Rumbo a zona 16', true], ['Me quedan 2 citas el viernes', false]],
                'highlights' => [['Diseños', ['French moderno', 'Baby boomer']], ['Antes y después', ['Pedicure spa', 'Acrílicas']]],
                'reviews' => [
                    [5, 'Kari es súper detallista. Mis uñas duraron 3 semanas.'],
                    [5, 'Vino a mi casa puntual y con todo esterilizado.', 'Gracias, Lucía. ¡Nos vemos en tres semanas!'],
                    [5, 'El diseño de flores quedó precioso.'],
                    [5, 'El pedicure spa es un lujo.'],
                    [4, 'Muy bonito trabajo, se tardó un poco más de lo que dijo.'],
                    [5, 'La recomiendo al 100 %.'],
                ],
            ],
            [
                'slug' => 'tejidosixchel', 'name' => 'Tejidos Ixchel', 'query' => 'huipil Guatemala', 'coverQuery' => 'backstrap loom weaving',
                'owner' => ['Ixchel Tzul', 'ixchel@test.gt'], 'plan' => 'free',
                'category' => 'artesania', 'offering' => 'productos', 'zone' => 'zona-1',
                'address' => 'Mercado Central, zona 1', 'whatsapp' => '55557890',
                'intro' => 'Huipiles y textiles tejidos a mano por mujeres de Sololá.',
                'about' => 'Cada pieza la teje una artesana de Sololá en telar de cintura. El 70 % de cada venta es para ella.',
                'hours' => [[[1, 2, 3, 4, 5, 6], '09:00', '17:00']],
                'fulfillment' => [FulfillmentMethod::Pickup, FulfillmentMethod::Shipping],
                'payments' => [PaymentMethod::BankTransfer, PaymentMethod::CashOnPickup],
                'products' => [
                    ['name' => 'Huipil bordado a mano', 'desc' => 'Pieza única. Tardó 3 meses en tejerse.', 'price' => 650, 'cost' => 480, 'mode' => StockMode::Inventory, 'stock' => 4, 'low' => 2],
                    ['name' => 'Bolsa de tela típica', 'desc' => 'Forro interior y cierre.', 'price' => 120, 'cost' => 70, 'mode' => StockMode::Inventory, 'stock' => 18, 'low' => 4],
                    ['name' => 'Servilletas bordadas', 'desc' => 'Juego de 4, colores surtidos.', 'variant' => 'Juego de 4', 'price' => 90, 'cost' => 50, 'mode' => StockMode::Inventory, 'stock' => 10, 'low' => 3],
                ],
                'posts' => [
                    ['Conoce a doña Juana', 'Ella tejió el huipil azul de la última foto. Lleva 40 años en el telar.', 3, false, 20],
                    ['Del telar a tu casa', 'Así se teje una bolsa, hilo por hilo.', 1, true, 200],
                ],
                'stories' => [['Nuevo huipil de Nahualá', false], ['En el telar hoy', true]],
                'highlights' => [['Artesanas', ['Doña Juana', 'Doña Petrona']]],
                'reviews' => [
                    [5, 'El huipil es una obra de arte. Vale cada quetzal.'],
                    [5, 'Me encanta saber quién tejió mi bolsa.', 'Gracias. Doña Juana te manda saludos.'],
                    [5, 'Las servilletas son bellísimas.'],
                    [4, 'Hermoso, pero el envío tardó una semana.'],
                    [5, 'Compré para regalar en el extranjero y quedaron fascinados.'],
                    [5, 'Trabajo impecable.'],
                ],
            ],
            [
                'slug' => 'plomeriaramirez', 'name' => 'Plomería Ramírez', 'query' => 'plumber', 'coverQuery' => 'plumbing pipes',
                'owner' => ['Luis Ramírez', 'luisr@test.gt'], 'plan' => 'free',
                'category' => 'reparaciones-y-oficios', 'offering' => 'servicios', 'zone' => 'villa-nueva',
                'whatsapp' => '55558901',
                'intro' => 'Destapes, fugas y calentadores. Llego el mismo día a Villa Nueva y zonas cercanas.',
                'about' => '15 años de experiencia. Te doy precio antes de empezar, sin sorpresas.',
                'hours' => [[[1, 2, 3, 4, 5, 6], '07:00', '18:00']],
                'modes' => [ServiceMode::AtCustomer],
                'zones' => [['villa-nueva', 0], ['zona-12', 25], ['san-miguel-petapa', 25]],
                'payments' => [PaymentMethod::CashOnDelivery],
                'services' => [
                    ['name' => 'Destape de drenaje', 'desc' => 'Con equipo de presión.', 'price_mode' => PriceMode::From, 'price' => 150, 'duration' => 60],
                    ['name' => 'Instalación de calentador', 'desc' => 'Eléctrico o de gas. Te cotizo según tu instalación.', 'price_mode' => PriceMode::Quote, 'duration' => 120],
                    ['name' => 'Revisión de fugas', 'desc' => 'Detecto y te digo cuánto cuesta arreglarla.', 'price' => 100, 'duration' => 45],
                ],
                'posts' => [
                    ['Antes y después de un destape', 'Cocina de Villa Nueva lista en 40 minutos.', 2, false, 36],
                ],
                'stories' => [['Rumbo a zona 12', false]],
                'highlights' => [],
                // Menos de 5 reseñas: el promedio se oculta y sale "Nuevo".
                'reviews' => [
                    [5, 'Llegó en una hora y arregló la fuga rápido.'],
                    [4, 'Buen trabajo, cobró lo que dijo.'],
                    [5, 'Muy honrado, lo recomiendo.'],
                ],
            ],
        ];
    }
}
