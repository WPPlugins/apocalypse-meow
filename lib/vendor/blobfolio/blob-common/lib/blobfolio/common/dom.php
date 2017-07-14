<?php
 namespace blobfolio\wp\meow\vendor\common; class dom { public static function load_svg($svg='') { ref\cast::to_string($svg, true); try { $svg = preg_replace('/<svg/ui', '<svg', $svg); $svg = preg_replace('/<\/svg>/ui', '</svg>', $svg); if ( false === ($start = mb::strpos($svg, '<svg')) || false === ($end = mb::strrpos($svg, '</svg>')) ) { return false; } $svg = mb::substr($svg, $start, ($end - $start + 6)); $svg = str_replace( array_keys(constants::SVG_ATTR_CORRECTIONS), array_values(constants::SVG_ATTR_CORRECTIONS), $svg ); $svg = preg_replace('/<\?(.*)\?>/Us', '', $svg); $svg = preg_replace('/<\%(.*)\%>/Us', '', $svg); if (false !== mb::strpos($svg, '<?') || false !== mb::strpos($svg, '<%')) { return false; } $svg = preg_replace('/<!--(.*)-->/Us', '', $svg); $svg = preg_replace('/\/\*(.*)\*\//Us', '', $svg); if (false !== mb::strpos($svg, '<!--') || false !== mb::strpos($svg, '/*')) { return false; } libxml_use_internal_errors(true); libxml_disable_entity_loader(true); $dom = new \DOMDocument('1.0', 'UTF-8'); $dom->formatOutput = false; $dom->preserveWhiteSpace = false; $dom->loadXML(constants::SVG_HEADER . "\n{$svg}"); $svgs = $dom->getElementsByTagName('svg'); if (!$svgs->length) { return false; } return $dom; } catch (\Throwable $e) { return false; } catch (\Exception $e) { return false; } } public static function save_svg(\DOMDocument $dom) { try { $svgs = $dom->getElementsByTagName('svg'); if (!$svgs->length) { return ''; } $svg = $svgs->item(0)->ownerDocument->saveXML( $svgs->item(0), LIBXML_NOBLANKS ); $svg = preg_replace('/xmlns\s*=\s*"[^"]*"/', 'xmlns="' . constants::SVG_NAMESPACE . '"', $svg); $svg = preg_replace('/<\?(.*)\?>/Us', '', $svg); $svg = preg_replace('/<\%(.*)\%>/Us', '', $svg); if (false !== mb::strpos($svg, '<?') || false !== mb::strpos($svg, '<%')) { return ''; } $svg = preg_replace('/<!--(.*)-->/Us', '', $svg); $svg = preg_replace('/\/\*(.*)\*\//Us', '', $svg); if (false !== mb::strpos($svg, '<!--') || false !== mb::strpos($svg, '/*')) { return ''; } if ( false === ($start = mb::strpos($svg, '<svg')) || false === ($end = mb::strrpos($svg, '</svg>')) ) { return false; } $svg = mb::substr($svg, $start, ($end - $start + 6)); return $svg; } catch (\Throwable $e) { return ''; } catch (\Exception $e) { return ''; } } public static function get_nodes_by_class($parent, $class=null, $all=false) { $nodes = array(); ref\cast::to_bool($all, true); try { if (!method_exists($parent, 'getElementsByTagName')) { return $nodes; } ref\cast::to_array($class); $class = array_map('trim', $class); foreach ($class as $k=>$v) { $class[$k] = ltrim($class[$k], '.'); } $class = array_filter($class, 'strlen'); sort($class); $class = array_unique($class); if (!count($class)) { return $nodes; } $possible = $parent->getElementsByTagName('*'); if ($possible->length) { foreach ($possible as $child) { if ($child->hasAttribute('class')) { $classes = $child->getAttribute('class'); ref\sanitize::whitespace($classes); $classes = explode(' ', $classes); $overlap = array_intersect($classes, $class); if (count($overlap) && (!$all || count($overlap) === count($class))) { $nodes[] = $child; } } } } } catch (\Throwable $e) { return $nodes; } catch (\Exception $e) { return $nodes; } return $nodes; } public static function parse_css($styles='') { ref\cast::to_string($styles, true); while (false !== $start = mb::strpos($styles, '/*')) { if (false !== $end = mb::strpos($styles, '*/')) { $styles = str_replace(mb::substr($styles, $start, ($end - $start + 2)), '', $styles); } else { $styles = mb::substr($styles, 0, $start); } } $styles = str_replace( array('<!--','//-->','-->','//<![CDATA[','//]]>','<![CDATA[',']]>'), '', $styles ); ref\sanitize::quotes($styles); $styles = str_replace("'", '"', $styles); ref\sanitize::whitespace($styles); if (!strlen($styles)) { return array(); } $styles = preg_replace('/\{(?![^"]*"(?:(?:[^"]*"){2})*[^"]*$)/u', '⠁', $styles); $styles = preg_replace('/\}(?![^"]*"(?:(?:[^"]*"){2})*[^"]*$)/u', '⠈', $styles); $styles = preg_replace('/\s*(\()\s*(?![^"]*"(?:(?:[^"]*"){2})*[^"]*$)/u', ' (', $styles); $styles = preg_replace('/\s*(\))\s*(?![^"]*"(?:(?:[^"]*"){2})*[^"]*$)/u', ') ', $styles); $styles = preg_replace('/\s*(⠁|⠈|@)\s*/u', '$1', $styles); $styles = str_replace('@', "\n@", $styles); $styles = explode("\n", $styles); $styles = array_map('trim', $styles); foreach ($styles as $k=>$v) { if (mb::substr($styles[$k], 0, 1) === '@') { if (mb::substr_count($styles[$k], '⠈⠈')) { $styles[$k] = preg_replace('/(⠈{2,})/u', "$1\n", $styles[$k]); } elseif (false !== mb::strpos($styles[$k], '⠈')) { $styles[$k] = str_replace('⠈', "⠈\n", $styles[$k]); } elseif (preg_match('/;(?![^"]*"(?:(?:[^"]*"){2})*[^"]*$)/', $styles[$k])) { $styles[$k] = preg_replace('/;(?![^"]*"(?:(?:[^"]*"){2})*[^"]*$)/u', ";\n", $styles[$k], 1); } $tmp = explode("\n", $styles[$k]); for ($x = 1; $x < count($tmp); $x++) { $tmp[$x] = str_replace('⠈', "⠈\n", $tmp[$x]); } $styles[$k] = implode("\n", $tmp); } else { $styles[$k] = str_replace('⠈', "⠈\n", $styles[$k]); } } $styles = implode("\n", $styles); $styles = preg_replace('/\)\s(,|;)(?![^"]*"(?:(?:[^"]*"){2})*[^"]*$)/u', ')$1', $styles); $styles = preg_replace('/(url|rgba?)\s+\(/', '$1(', $styles); $styles = explode("\n", $styles); $styles = array_filter($styles, 'strlen'); $out = array(); foreach ($styles as $k=>$v) { $styles[$k] = trim($styles[$k]); if (mb::substr($styles[$k], 0, 1) === '@' && mb::substr_count($styles[$k], '⠈⠈')) { $tmp = constants::CSS_NESTED; preg_match_all('/^@([a-z\-]+)/ui', $styles[$k], $matches); $tmp['@'] = mb::strtolower($matches[1][0]); if (false === $start = mb::strpos($styles[$k], '⠁')) { continue; } $tmp['selector'] = mb::strtolower(trim(mb::substr($styles[$k], 0, $start))); $chunk = mb::substr($styles[$k], $start + 1, -1); $chunk = str_replace(array('⠁','⠈'), array('{','}'), $chunk); $tmp['nest'] = static::parse_css($chunk); $tmp['raw'] = $tmp['selector'] . '{'; foreach ($tmp['nest'] as $n) { $tmp['raw'] .= $n['raw']; } $tmp['raw'] .= '}'; } else { $tmp = constants::CSS_FLAT; if (mb::substr($styles[$k], 0, 1) === '@') { preg_match_all('/^@([a-z\-]+)/ui', $styles[$k], $matches); $tmp['@'] = mb::strtolower($matches[1][0]); } preg_match_all('/^([^⠁]+)⠁([^⠈]*)⠈/u', $styles[$k], $matches); if (count($matches[0])) { $tmp['selectors'] = explode(',', $matches[1][0]); $tmp['selectors'] = array_map('trim', $tmp['selectors']); $rules = explode(';', $matches[2][0]); $rules = array_map('trim', $rules); $rules = array_filter($rules, 'strlen'); if (!count($rules)) { continue; } foreach ($rules as $k2=>$v2) { $rules[$k2] = rtrim($rules[$k2], ';') . ';'; if (preg_match('/:(?![^"]*"(?:(?:[^"]*"){2})*[^"]*$)/', $rules[$k2])) { $rules[$k2] = preg_replace('/:(?![^"]*"(?:(?:[^"]*"){2})*[^"]*$)/u', "\n", $rules[$k2], 1); list($key, $value) = explode("\n", $rules[$k2]); $key = mb::strtolower(trim($key)); $value = trim($value); $tmp['rules'][$key] = $value; } else { $tmp['rules']['__NONE__'] = $value; } } $tmp['raw'] = implode(',', $tmp['selectors']) . '{'; foreach ($tmp['rules'] as $k=>$v) { if ('__NONE__' === $k) { $tmp['raw'] .= $v; } else { $tmp['raw'] .= "$k:$v"; } } $tmp['raw'] .= '}'; } else { $styles[$k] = str_replace(array('⠁','⠈'), array('{','}'), $styles[$k]); $styles[$k] = trim(rtrim(trim($styles[$k]), ';')); if (mb::substr($styles[$k], -1) !== '}') { $styles[$k] .= ';'; } $tmp['rules'][] = $styles[$k]; $tmp['raw'] = $styles[$k]; } } $out[] = $tmp; } return $out; } public static function remove_namespace($dom, $namespace) { if ( !is_a($dom, 'DOMDocument') || !is_string($namespace) || !strlen($namespace) ) { return false; } try { $xpath = new \DOMXPath($dom); $nodes = $xpath->query("//*[namespace::{$namespace} and not(../namespace::{$namespace})]"); for ($x = 0; $x < $nodes->length; $x++) { $node = $nodes->item($x); $node->removeAttributeNS( $node->lookupNamespaceURI($namespace), $namespace ); } return true; } catch (\Throwable $e) { return false; } catch (\Exception $e) { return false; } return false; } public static function remove_nodes(\DOMNodeList $nodes) { try { while ($nodes->length) { static::remove_node($nodes->item(0)); } } catch (\Throwable $e) { return false; } catch (\Exception $e) { return false; } return true; } public static function remove_node($node) { if ( !is_a($node, 'DOMElement') && !is_a($node, 'DOMNode') ) { return false; } try { $node->parentNode->removeChild($node); } catch (\Throwable $e) { return false; } catch (\Exception $e) { return false; } return true; } } 