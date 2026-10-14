<?php
// SPDX-License-Identifier: GPL-3.0-or-later
foreach (['configuration','database','identity','access','personservice','adminservice'] as $file) require_once __DIR__.'/'.$file.'.php';
