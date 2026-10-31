<?php

/*
 *
 *      _    _ _
 *     / \  | | |_ __ _ _   _
 *    / _ \ | | __/ _` | | | |
 *   / ___ \| | || (_| | |_| |
 *  /_/   \_\_|\__\__,_|\__, |
 *                       |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Altay Team
 * @link https://github.com/altayofficial
 */


declare(strict_types=1);

namespace altay\bedrockstructure\io;

use altay\bedrockstructure\StructureException;
use pocketmine\errorhandler\ErrorToExceptionHandler;
use pocketmine\utils\AssumptionFailedError;
use function zlib_decode;
use function zlib_encode;
use const ZLIB_ENCODING_DEFLATE;

/**
 * How the structure tag is stored. With ZLIB it's a byte array holding a full NBT document of its
 * own, root header and all, not just the compound's contents.
 */
enum Compression : int{
	case NONE = 0;
	case ZLIB = 1;

	/**
	 * Cap for an inflated structure tag. A maxed out structure block export (64x384x64) is around 13MB
	 * of indices, so this leaves plenty of room for palettes and block entities.
	 */
	public const MAX_DECOMPRESSED_SIZE = 128 * 1024 * 1024;

	public function compress(string $data) : string{
		if($this === self::NONE){
			return $data;
		}
		$compressed = zlib_encode($data, ZLIB_ENCODING_DEFLATE);
		if($compressed === false){
			throw new AssumptionFailedError("zlib_encode() shouldn't fail with a valid encoding");
		}
		return $compressed;
	}

	/**
	 * @throws StructureException
	 */
	public function decompress(string $data) : string{
		if($this === self::NONE){
			return $data;
		}
		try{
			return ErrorToExceptionHandler::trapAndRemoveFalse(fn() => zlib_decode($data, self::MAX_DECOMPRESSED_SIZE));
		}catch(\ErrorException $e){
			throw new StructureException("Failed to decompress structure: " . $e->getMessage(), 0, $e);
		}
	}
}
