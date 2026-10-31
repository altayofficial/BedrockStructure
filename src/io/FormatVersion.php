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

use altay\bedrockstructure\io\layer\BlockLayerCodec;
use altay\bedrockstructure\io\layer\IntArrayBlockLayerCodec;
use altay\bedrockstructure\io\layer\ListBlockLayerCodec;

enum FormatVersion : int{
	/** Exactly two block index layers, both lists of int tags. Vanilla packs still ship this one. */
	case V1 = 1;
	/**
	 * One or two block index layers stored as int arrays, the second one only when something is
	 * waterlogged. Clients since 1.26.50 write this and can also zlib compress the structure tag.
	 */
	case V2 = 2;

	/**
	 * How this version lays out the block index layers.
	 */
	public function layerCodec() : BlockLayerCodec{
		return match($this){
			self::V1 => new ListBlockLayerCodec(),
			self::V2 => new IntArrayBlockLayerCodec(),
		};
	}

	public function supportsCompression() : bool{
		return $this === self::V2;
	}
}
