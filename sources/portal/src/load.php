<?php
// SPDX-License-Identifier: GPL-3.0-or-later
foreach (['configuration','database','identity','access','personservice','adminservice','branding','journeycontext','candidateservice','contentservice','welcomeservice'] as $file) require_once __DIR__.'/'.$file.'.php';
