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

/**
 * Tag names used by the .mcstructure root and structure compounds.
 */
final class StructureTags{
	public const FORMAT_VERSION = "format_version";
	public const COMPRESSION = "compression";
	public const SIZE = "size";
	public const ORIGIN = "structure_world_origin";
	public const STRUCTURE = "structure";
	public const BLOCK_INDICES = "block_indices";
	public const ENTITIES = "entities";
	public const PALETTE = "palette";

	private function __construct(){
		//NOOP
	}
}
