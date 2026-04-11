<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\File;

$originalPackageJson = null;

beforeEach( function () use ( &$originalPackageJson ): void {
	$path = base_path( 'package.json' );

	if ( File::exists( $path ) ) {
		$originalPackageJson = File::get( $path );
	} else {
		$originalPackageJson = null;
	}
} );

afterEach( function () use ( &$originalPackageJson ): void {
	$path = base_path( 'package.json' );

	if ( null !== $originalPackageJson ) {
		File::put( $path, $originalPackageJson );
	} elseif ( File::exists( $path ) ) {
		File::delete( $path );
	}
} );

test( 'install-frontend command requires --stack option', function (): void {
	$this->artisan( 'analytics:install-frontend' )
		->assertFailed();
} );

test( 'install-frontend command rejects invalid stack', function (): void {
	$this->artisan( 'analytics:install-frontend', [ '--stack' => 'svelte' ] )
		->assertFailed();
} );

test( 'install-frontend command accepts react stack', function (): void {
	$this->artisan( 'analytics:install-frontend', [ '--stack' => 'react' ] )
		->assertSuccessful();
} );

test( 'install-frontend command accepts vue stack', function (): void {
	$this->artisan( 'analytics:install-frontend', [ '--stack' => 'vue' ] )
		->assertSuccessful();
} );

test( 'install-frontend command is case insensitive for stack', function (): void {
	$this->artisan( 'analytics:install-frontend', [ '--stack' => 'React' ] )
		->assertSuccessful();
} );

test( 'install-frontend react adds dependencies to package.json', function (): void {
	$packageJsonPath = base_path( 'package.json' );

	File::put( $packageJsonPath, json_encode( [
		'name'         => 'test-app',
		'dependencies' => [],
	], JSON_PRETTY_PRINT ) );

	$this->artisan( 'analytics:install-frontend', [ '--stack' => 'react' ] )
		->assertSuccessful();

	$packageJson = json_decode( File::get( $packageJsonPath ), true );

	expect( $packageJson['dependencies'] )
		->toHaveKey( '@artisanpack-ui/react' )
		->toHaveKey( '@artisanpack-ui/tokens' )
		->toHaveKey( 'react' )
		->toHaveKey( 'react-dom' )
		->toHaveKey( 'react-apexcharts' )
		->toHaveKey( 'apexcharts' );
} );

test( 'install-frontend vue adds dependencies to package.json', function (): void {
	$packageJsonPath = base_path( 'package.json' );

	File::put( $packageJsonPath, json_encode( [
		'name'         => 'test-app',
		'dependencies' => [],
	], JSON_PRETTY_PRINT ) );

	$this->artisan( 'analytics:install-frontend', [ '--stack' => 'vue' ] )
		->assertSuccessful();

	$packageJson = json_decode( File::get( $packageJsonPath ), true );

	expect( $packageJson['dependencies'] )
		->toHaveKey( '@artisanpack-ui/vue' )
		->toHaveKey( '@artisanpack-ui/tokens' )
		->toHaveKey( 'vue' )
		->toHaveKey( 'vue3-apexcharts' )
		->toHaveKey( 'apexcharts' );
} );

test( 'install-frontend does not overwrite existing dependencies', function (): void {
	$packageJsonPath = base_path( 'package.json' );

	File::put( $packageJsonPath, json_encode( [
		'name'         => 'test-app',
		'dependencies' => [
			'react' => '^17.0',
		],
	], JSON_PRETTY_PRINT ) );

	$this->artisan( 'analytics:install-frontend', [ '--stack' => 'react' ] )
		->assertSuccessful();

	$packageJson = json_decode( File::get( $packageJsonPath ), true );

	expect( $packageJson['dependencies']['react'] )->toBe( '^17.0' );

	expect( $packageJson['dependencies'] )
		->toHaveKey( '@artisanpack-ui/react' )
		->toHaveKey( 'apexcharts' );
} );

test( 'install-frontend succeeds when package.json is missing', function (): void {
	$packageJsonPath = base_path( 'package.json' );

	if ( File::exists( $packageJsonPath ) ) {
		File::delete( $packageJsonPath );
	}

	$this->artisan( 'analytics:install-frontend', [ '--stack' => 'react' ] )
		->assertSuccessful();
} );

test( 'install-frontend command sorts dependencies alphabetically', function (): void {
	$packageJsonPath = base_path( 'package.json' );

	File::put( $packageJsonPath, json_encode( [
		'name'         => 'test-app',
		'dependencies' => [
			'zod' => '^3.0',
		],
	], JSON_PRETTY_PRINT ) );

	$this->artisan( 'analytics:install-frontend', [ '--stack' => 'react' ] )
		->assertSuccessful();

	$packageJson = json_decode( File::get( $packageJsonPath ), true );
	$keys        = array_keys( $packageJson['dependencies'] );
	$sorted      = $keys;
	sort( $sorted );

	expect( $keys )->toBe( $sorted );
} );
