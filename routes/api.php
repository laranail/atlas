<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\Atlas\Http\Controllers\IpController;
use Simtabi\Laranail\Atlas\Http\Controllers\CountryController;
use Simtabi\Laranail\Atlas\Http\Controllers\DistanceController;
use Simtabi\Laranail\Atlas\Http\Controllers\CatalogueController;

/*
| Loaded ONLY when laranail.atlas.api.enabled is true. The prefix, version and
| middleware come from config; this file names the endpoints and nothing else.
|
| Every route is a GET and nothing here writes. That is what makes this surface
| safe to expose and trivial to cache — the catalogue changes when the package
| is upgraded, not when a request arrives.
*/

Route::get('/countries', [CountryController::class, 'index'])->name('laranail.atlas.countries.index');
Route::get('/currencies', [CatalogueController::class, 'currencies'])->name('laranail.atlas.currencies');
Route::get('/languages', [CatalogueController::class, 'languages'])->name('laranail.atlas.languages');
Route::get('/continents', [CatalogueController::class, 'continents'])->name('laranail.atlas.continents');
Route::get('/regions', [CatalogueController::class, 'regions'])->name('laranail.atlas.regions');
Route::get('/subregions', [CatalogueController::class, 'subregions'])->name('laranail.atlas.subregions');
Route::get('/distance', DistanceController::class)->name('laranail.atlas.distance');
Route::get('/describe', [CatalogueController::class, 'describe'])->name('laranail.atlas.describe');

// Deliberately unconstrained. A `where()` pattern looks like validation and is
// not: anything it rejects becomes a 404 saying the endpoint does not exist,
// while `ff.ff.ff.ff` matches any plausible pattern and is still not an
// address. The controller parses properly and answers 422 with a field name,
// so every malformed input gets the same answer instead of two depending on
// which characters it happened to use.
Route::get('/ip/{ip}', IpController::class)->name('laranail.atlas.ip');

Route::get('/countries/{code}', [CountryController::class, 'show'])->name('laranail.atlas.countries.show');
