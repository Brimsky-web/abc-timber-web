<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Create companies table first (referenced by users)
        Schema::create("companies", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("slug")->unique();
            $table->text("description")->nullable();
            $table->string("website")->nullable();
            $table->string("email")->nullable();
            $table->string("phone")->nullable();
            $table->json("address")->nullable();
            $table->boolean("is_active")->default(true);
            $table->timestamps();

            $table->index("slug");
            $table->index("is_active");
        });

        // Create photos table first (referenced by users)
        Schema::create("photos", function (Blueprint $table) {
            $table->id();
            $table->string("filename");
            $table->string("original_name");
            $table->string("mime_type");
            $table->integer("size");
            $table->integer("width")->nullable();
            $table->integer("height")->nullable();
            $table->string("path");
            $table->string("disk")->default("public");
            $table->json("metadata")->nullable();
            $table->timestamps();

            $table->index("mime_type");
            $table->index("created_at");
        });

        // Create users table with foreign key references
        Schema::create("users", function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->string("name");
            $table->string("surname")->nullable();
            $table->string("email")->unique();
            $table->timestamp("email_verified_at")->nullable();
            $table->string("password");
            $table->string("phone")->nullable();
            $table->date("date_of_birth")->nullable();

            // Role and subscription
            $table
                ->enum("role", [
                    "free",
                    "basic",
                    "premium",
                    "enterprise",
                    "admin",
                ])
                ->default("free");
            $table->boolean("is_active")->default(true);
            $table->boolean("is_admin")->default(false);

            // OAuth fields
            $table->string("google_id")->nullable()->unique();
            $table->string("avatar")->nullable();

            // Two-factor authentication
            $table->boolean("two_factor_enabled")->default(false);
            $table->string("two_factor_secret")->nullable();
            $table->json("two_factor_recovery_codes")->nullable();
            $table->timestamp("two_factor_confirmed_at")->nullable();

            // Profile relationships
            $table
                ->foreignId("profile_photo_id")
                ->nullable()
                ->constrained("photos")
                ->nullOnDelete();
            $table
                ->foreignId("company_id")
                ->nullable()
                ->constrained("companies")
                ->nullOnDelete();

            // API usage tracking for rate limiting
            $table->integer("api_calls_this_hour")->default(0);
            $table->timestamp("api_calls_reset_at")->nullable();
            $table->bigInteger("total_api_calls")->default(0);

            // Storage usage tracking
            $table->bigInteger("storage_used_bytes")->default(0);

            // Login tracking
            $table->timestamp("last_login_at")->nullable();
            $table->string("last_login_ip")->nullable();

            $table->rememberToken();
            $table->timestamps();

            // Indexes for performance
            $table->index(["role", "is_active"]);
            $table->index("email_verified_at");
            $table->index("last_login_at");
            $table->index("id"); // For UUID lookups
        });

        // Personal Access Tokens table for Laravel Sanctum (only if it doesn't exist)
        if (!Schema::hasTable("personal_access_tokens")) {
            Schema::create("personal_access_tokens", function (
                Blueprint $table,
            ) {
                $table->id();
                $table->uuidMorphs("tokenable");
                $table->string("name");
                $table->string("token", 64)->unique();
                $table->text("abilities")->nullable();
                $table->timestamp("last_used_at")->nullable();
                $table->timestamp("expires_at")->nullable();
                $table->timestamps();

                // Additional indexes (uuidMorphs already creates the main indexes)
                $table->index("token");
                $table->index("last_used_at");
                $table->index("expires_at");
            });
        }

        // Enhanced password reset tokens
        Schema::create("password_reset_tokens", function (Blueprint $table) {
            $table->id();
            $table->string("email");
            $table->string("token", 64);
            $table->timestamp("expires_at");
            $table->boolean("used")->default(false);
            $table->string("ip_address")->nullable();
            $table->timestamps();

            $table->index(["email", "token"]);
            $table->index("expires_at");
        });

        // Email verification tokens
        Schema::create("email_verification_tokens", function (
            Blueprint $table,
        ) {
            $table->id();
            $table->string("email");
            $table->string("token", 64);
            $table->timestamp("expires_at");
            $table->timestamps();

            $table->index(["email", "token"]);
            $table->index("expires_at");
        });

        // OAuth social accounts
        Schema::create("social_accounts", function (Blueprint $table) {
            $table->id();
            $table->uuid("user_id");
            $table->string("provider");
            $table->string("provider_id");
            $table->string("provider_email")->nullable();
            $table->json("provider_data")->nullable();
            $table->timestamps();

            $table
                ->foreign("user_id")
                ->references("id")
                ->on("users")
                ->cascadeOnDelete();
            $table->unique(["provider", "provider_id"]);
            $table->index("user_id");
        });

        // Sessions table
        Schema::create("sessions", function (Blueprint $table) {
            $table->string("id")->primary();
            $table->uuid("user_id")->nullable()->index();
            $table->string("ip_address", 45)->nullable();
            $table->text("user_agent")->nullable();
            $table->longText("payload");
            $table->integer("last_activity")->index();

            $table
                ->foreign("user_id")
                ->references("id")
                ->on("users")
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("sessions");
        Schema::dropIfExists("social_accounts");
        Schema::dropIfExists("email_verification_tokens");
        Schema::dropIfExists("password_reset_tokens");
        // Only drop personal_access_tokens if we created it
        if (
            Schema::hasColumn("personal_access_tokens", "tokenable_type") &&
            Schema::getColumnType(
                "personal_access_tokens",
                "tokenable_type",
            ) === "string"
        ) {
            Schema::dropIfExists("personal_access_tokens");
        }
        Schema::dropIfExists("users");
        Schema::dropIfExists("photos");
        Schema::dropIfExists("companies");
    }
};
