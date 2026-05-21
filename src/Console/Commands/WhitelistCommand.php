<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Console\Commands;

use ArtisanPackUI\Analytics\Models\BotWhitelistEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Command to manage the bot detection whitelist.
 *
 * Lists the combined config and database whitelist, and adds or removes
 * runtime (database) entries. Config entries are read-only here.
 *
 * @since   1.2.0
 *
 * @package ArtisanPackUI\Analytics\Console\Commands
 */
class WhitelistCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'analytics:whitelist
		{action=list : The action to perform (list, add, remove)}
		{--user-agent= : A user agent pattern to add or remove}
		{--ip= : An IP address to add or remove}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Manage the bot detection whitelist (list, add, remove)';

	/**
	 * Execute the console command.
	 *
	 * @return int
	 *
	 * @since 1.2.0
	 */
	public function handle(): int
	{
		return match ( $this->argument( 'action' ) ) {
			'list'   => $this->list(),
			'add'    => $this->add(),
			'remove' => $this->remove(),
			default  => $this->invalidAction(),
		};
	}

	/**
	 * List the combined config and database whitelist entries.
	 *
	 * @return int
	 *
	 * @since 1.2.0
	 */
	protected function list(): int
	{
		$rows = [];

		/** @var array<int, string> $configAgents */
		$configAgents = config( 'artisanpack.analytics.bot_detection.whitelist.user_agents', [] );
		foreach ( $configAgents as $value ) {
			$rows[] = [ __( 'User Agent' ), $value, __( 'config' ) ];
		}

		/** @var array<int, string> $configIps */
		$configIps = config( 'artisanpack.analytics.bot_detection.whitelist.ips', [] );
		foreach ( $configIps as $value ) {
			$rows[] = [ __( 'IP' ), $value, __( 'config' ) ];
		}

		if ( $this->ensureTableExists( false ) ) {
			foreach ( BotWhitelistEntry::query()->orderBy( 'type' )->orderBy( 'value' )->get() as $entry ) {
				$rows[] = [
					BotWhitelistEntry::TYPE_IP === $entry->type ? __( 'IP' ) : __( 'User Agent' ),
					$entry->value,
					__( 'database' ),
				];
			}
		}

		if ( [] === $rows ) {
			$this->info( __( 'The bot whitelist is empty.' ) );

			return self::SUCCESS;
		}

		$this->table( [ __( 'Type' ), __( 'Value' ), __( 'Source' ) ], $rows );

		return self::SUCCESS;
	}

	/**
	 * Add a user agent or IP entry to the database whitelist.
	 *
	 * @return int
	 *
	 * @since 1.2.0
	 */
	protected function add(): int
	{
		if ( ! $this->ensureTableExists() ) {
			return self::FAILURE;
		}

		[ $type, $value ] = $this->resolveTarget();

		if ( null === $type ) {
			return self::FAILURE;
		}

		$entry = BotWhitelistEntry::query()->firstOrCreate( [
			'type'  => $type,
			'value' => $value,
		] );

		if ( $entry->wasRecentlyCreated ) {
			$this->info( sprintf( __( 'Added "%s" to the whitelist.' ), $value ) );
		} else {
			$this->info( sprintf( __( '"%s" is already in the whitelist.' ), $value ) );
		}

		return self::SUCCESS;
	}

	/**
	 * Remove a user agent or IP entry from the database whitelist.
	 *
	 * @return int
	 *
	 * @since 1.2.0
	 */
	protected function remove(): int
	{
		if ( ! $this->ensureTableExists() ) {
			return self::FAILURE;
		}

		[ $type, $value ] = $this->resolveTarget();

		if ( null === $type ) {
			return self::FAILURE;
		}

		$deleted = BotWhitelistEntry::query()
			->where( 'type', $type )
			->where( 'value', $value )
			->delete();

		if ( 0 === $deleted ) {
			$this->warn( sprintf( __( '"%s" was not found in the database whitelist. Config entries cannot be removed here.' ), $value ) );

			return self::SUCCESS;
		}

		$this->info( sprintf( __( 'Removed "%s" from the whitelist.' ), $value ) );

		return self::SUCCESS;
	}

	/**
	 * Resolve the whitelist target type and value from the options.
	 *
	 * @return array{0: string|null, 1: string|null}
	 *
	 * @since 1.2.0
	 */
	protected function resolveTarget(): array
	{
		$userAgent = $this->option( 'user-agent' );
		$ip        = $this->option( 'ip' );

		if ( ( null === $userAgent || '' === $userAgent ) && ( null === $ip || '' === $ip ) ) {
			$this->error( __( 'You must provide either --user-agent or --ip.' ) );

			return [ null, null ];
		}

		if ( ( null !== $userAgent && '' !== $userAgent ) && ( null !== $ip && '' !== $ip ) ) {
			$this->error( __( 'Provide only one of --user-agent or --ip, not both.' ) );

			return [ null, null ];
		}

		if ( null !== $userAgent && '' !== $userAgent ) {
			return [ BotWhitelistEntry::TYPE_USER_AGENT, $userAgent ];
		}

		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$this->error( __( 'Invalid --ip value. Provide a valid IP address.' ) );

			return [ null, null ];
		}

		return [ BotWhitelistEntry::TYPE_IP, $ip ];
	}

	/**
	 * Ensure the whitelist table has been migrated before querying it.
	 *
	 * @param bool $reportError Whether to emit a command error when the table is missing.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	protected function ensureTableExists( bool $reportError = true ): bool
	{
		if ( Schema::hasTable( ( new BotWhitelistEntry() )->getTable() ) ) {
			return true;
		}

		if ( $reportError ) {
			$this->error( __( 'The bot whitelist table was not found. Run migrations first.' ) );
		}

		return false;
	}

	/**
	 * Report an invalid action.
	 *
	 * @return int
	 *
	 * @since 1.2.0
	 */
	protected function invalidAction(): int
	{
		$this->error( sprintf( __( 'Unknown action "%s". Use list, add, or remove.' ), (string) $this->argument( 'action' ) ) );

		return self::FAILURE;
	}
}
