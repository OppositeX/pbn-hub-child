<?php
/**
 * Local build helper — packages the child plugin source into ../dist/pbn-hub-child.zip.
 * Usage: php tools/build-zip.php
 */
if ( PHP_SAPI !== 'cli' ) { fwrite( STDERR, "CLI only.\n" ); exit( 1 ); }

$root = realpath( __DIR__ . '/..' );
$dist = $root . '/dist';
$work = $dist . '/pbn-hub-child';
if ( ! is_dir( $dist ) ) mkdir( $dist, 0755, true );
if ( is_dir( $work ) )   passthru( 'rm -rf ' . escapeshellarg( $work ) );
mkdir( $work, 0755, true );

$exclude = [ '.git', '.github', 'tools', 'tests', '.gitignore', 'README.md', 'dist', 'node_modules' ];

$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
foreach ( $it as $path => $info ) {
    $rel = substr( $path, strlen( $root ) + 1 );
    $top = explode( DIRECTORY_SEPARATOR, $rel )[0];
    if ( in_array( $top, $exclude, true ) ) continue;
    $dst = $work . '/' . $rel;
    if ( $info->isDir() ) @mkdir( $dst, 0755, true );
    else copy( $path, $dst );
}

$zip_path = $dist . '/pbn-hub-child.zip';
@unlink( $zip_path );
$zip = new ZipArchive();
$zip->open( $zip_path, ZipArchive::CREATE );
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $work, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
foreach ( $it as $path => $info ) {
    $rel = 'pbn-hub-child/' . substr( $path, strlen( $work ) + 1 );
    if ( $info->isDir() ) $zip->addEmptyDir( $rel );
    else $zip->addFile( $path, $rel );
}
$zip->close();
echo "Built: {$zip_path}\n";
