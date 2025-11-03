<?php

use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\CarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PriceHistoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware("auth")->group(function () {
	Route::get("/", [DashboardController::class, "view"]);

	Route::get("c", [CarController::class, "index"]);
	Route::get("c/new", [CarController::class, "new"]);
	Route::post("c/new", [CarController::class, "create"]);
	Route::get("c/{car}", [CarController::class, "view"])->withTrashed();
	Route::post("c/{car}", [CarController::class, "update"])
		->withTrashed()
		->can("update", "car");

	Route::middleware("can:isAdmin")->group(function () {
		Route::get("import", [ImportController::class, "view"]);
		Route::post("import", [ImportController::class, "import"]);

		Route::get("u", [UserController::class, "index"]);
		Route::get("u/new", [UserController::class, "new"]);
		Route::post("u/new", [UserController::class, "create"]);
		Route::get("u/{user}", [UserController::class, "view"])->withTrashed();
		Route::post("u/{user}", [UserController::class, "update"])->withTrashed();

		Route::post("u/{user}/impersonate", [AuthenticationController::class, "impersonate"]);
	});

	Route::get("t", [TransactionController::class, "index"]);
	Route::get("t/new", [TransactionController::class, "new"]);
	Route::post("t/new", [TransactionController::class, "create"]);
	Route::get("t/{transaction}", [TransactionController::class, "view"])
		->can("view", "transaction")
		->withTrashed();
	Route::post("t/{transaction}", [TransactionController::class, "update"])
		->can("update", "transaction")
		->withTrashed();

	Route::get("price-history", [PriceHistoryController::class, "view"]);

	Route::get("export", [ExportController::class, "view"]);
	Route::post("export", [ExportController::class, "export"]);

	Route::get("profile", [ProfileController::class, "view"]);
	Route::post("profile", [ProfileController::class, "update"]);
	Route::get("change-password", [ProfileController::class, "changePassword"]);
	Route::post("change-password", [ProfileController::class, "doChangePassword"]);

	Route::get("logout", [AuthenticationController::class, "logout"]);

	Route::get("calculate/trip-price", [CalculatorController::class, "tripPrice"]);
});

Route::middleware("guest")->group(function () {
	Route::get("login", [AuthenticationController::class, "view"])->name("login");
	Route::post("login", [AuthenticationController::class, "login"]);
});

Route::view("temp", "pages.temp");
