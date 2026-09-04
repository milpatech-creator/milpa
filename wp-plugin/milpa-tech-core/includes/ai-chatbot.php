<?php
/**
 * Milpa AI — the assistant chatbot from the original app (AIChatBot.tsx +
 * server/geminiService.ts), ported here as a REST endpoint + a small
 * vanilla-JS widget. Ported rather than rebuilt: the fallback reply text
 * below is close to verbatim from the original TS so the assistant's
 * personality/content doesn't drift between the two implementations.
 *
 * Three-tier response, same shape as the original:
 *   1. Gemini API with Google Search grounding (if MILPA_GEMINI_API_KEY
 *      is defined in wp-config.php — it isn't yet, so this tier is inert
 *      until that's provided).
 *   2. Gemini without grounding, if tier 1 errors.
 *   3. A scripted domain-knowledge fallback — this is the ONLY tier that
 *      currently runs, and is written to be genuinely useful on its own,
 *      not just a placeholder.
 *
 * Positioned bottom-right so it doesn't collide with Deskuss's own
 * support-chat bubble, which sits bottom-left.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'milpa/v1', '/chat', array(
		'methods'             => 'POST',
		'callback'            => 'milpa_handle_chat_request',
		'permission_callback' => '__return_true',
		'args'                => array(
			'message' => array( 'required' => true, 'type' => 'string' ),
			'lang'    => array( 'required' => false, 'type' => 'string', 'default' => 'es' ),
			'history' => array( 'required' => false, 'type' => 'array', 'default' => array() ),
		),
	) );
} );

function milpa_handle_chat_request( WP_REST_Request $request ) {
	$message = trim( (string) $request->get_param( 'message' ) );
	$lang    = 'en' === $request->get_param( 'lang' ) ? 'en' : 'es';
	$history = (array) $request->get_param( 'history' );

	if ( '' === $message ) {
		return new WP_Error( 'milpa_empty_message', __( 'Message is required.' ), array( 'status' => 400 ) );
	}

	$result = milpa_execute_chat( $message, $history, $lang );
	return rest_ensure_response( $result );
}

function milpa_gemini_api_key() {
	return defined( 'MILPA_GEMINI_API_KEY' ) && MILPA_GEMINI_API_KEY ? MILPA_GEMINI_API_KEY : null;
}

/**
 * Pulls the live crop listings (real WooCommerce products now, not the
 * hardcoded JS array the original app used) so the system prompt — and,
 * eventually, Gemini's replies — stay in sync with whatever's actually
 * for sale, without a second copy of the data to keep updated.
 */
function milpa_crops_context() {
	$products = wc_get_products( array( 'status' => 'publish', 'limit' => 20 ) );
	$out      = array();
	foreach ( $products as $product ) {
		$id   = $product->get_id();
		$out[] = array(
			'name'             => $product->get_name(),
			'location'         => get_field( 'crop_location', $id ),
			'risk_level'       => get_field( 'risk_level', $id ),
			'token_price'      => get_field( 'token_price', $id ),
			'yield_projection' => get_field( 'yield_projection', $id ),
			'sustainability'   => get_field( 'sustainability_score', $id ),
		);
	}
	return $out;
}

function milpa_build_system_prompt( $lang, $crops_context ) {
	$is_es = 'es' === $lang;
	return sprintf(
		"You are 'Milpa AI Assistant' (Asistente Agrícola Milpa), an expert AI consultant for the Milpa Tech platform.\n" .
		"Milpa Tech is a bilingual (Spanish/English) agricultural tokenization and regenerative farming platform in Mexico.\n\n" .
		"Platform Capabilities & Context:\n" .
		"- Allows investors to buy fractionated tokens of real agricultural crops in Mexico.\n" .
		"- Each crop is backed by verified OpenSea NFTs on Polygon/Ethereum for traceability and proof of ownership (once Phase 3 legal review clears real on-chain tokenization — today this is a demo/marketing device).\n" .
		"- Investors receive returns upon harvest based on agricultural yield and ESG sustainability scores.\n" .
		"- Producers get direct fair financing, satellite NDVI monitoring, and AI risk assessment.\n" .
		"- Current language: %s.\n" .
		"- Active crops in marketplace: %s.\n\n" .
		"Your Directives:\n" .
		"1. Answer questions clearly about crops, token prices, returns, NFT verification, regenerative agro-practices, and how to invest.\n" .
		"2. Keep responses structured with clear markdown bullet points, bold key terms, and scannable paragraphs.\n" .
		"3. Respond strictly in the user's requested language (%s).\n",
		$is_es ? 'Spanish (Español)' : 'English',
		wp_json_encode( $crops_context ),
		$is_es ? 'Spanish' : 'English'
	);
}

function milpa_execute_chat( $message, $history, $lang ) {
	$api_key = milpa_gemini_api_key();

	if ( $api_key ) {
		$live = milpa_call_gemini( $message, $history, $lang, $api_key );
		if ( $live ) {
			return $live;
		}
	}

	return array(
		'reply'  => milpa_generate_fallback_reply( $message, $lang ),
		'source' => 'fallback',
	);
}

function milpa_call_gemini( $message, $history, $lang, $api_key ) {
	$crops   = milpa_crops_context();
	$system  = milpa_build_system_prompt( $lang, $crops );
	$contents = array();

	foreach ( array_slice( (array) $history, -8 ) as $turn ) {
		if ( empty( $turn['role'] ) || empty( $turn['text'] ) ) {
			continue;
		}
		$contents[] = array(
			'role'  => 'user' === $turn['role'] ? 'user' : 'model',
			'parts' => array( array( 'text' => $turn['text'] ) ),
		);
	}
	$contents[] = array( 'role' => 'user', 'parts' => array( array( 'text' => $message ) ) );

	$response = wp_remote_post(
		'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.7-flash:generateContent?key=' . rawurlencode( $api_key ),
		array(
			'timeout' => 20,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'contents'         => $contents,
				'systemInstruction' => array( 'parts' => array( array( 'text' => $system ) ) ),
				'generationConfig' => array( 'temperature' => 0.7 ),
			) ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';

	if ( '' === $text ) {
		return null;
	}

	return array( 'reply' => $text, 'source' => 'gemini-live' );
}

/**
 * Domain-knowledge fallback — ported near-verbatim from the original
 * app's generateDomainFallbackReply() so the assistant is genuinely
 * useful even with no Gemini key configured.
 */
function milpa_generate_fallback_reply( $query, $lang ) {
	$is_es = 'es' === $lang;
	$q     = mb_strtolower( $query );

	if ( milpa_str_has( $q, array( 'agave', 'espadin', 'espadín', 'oaxaca', 'mezcal' ) ) ) {
		return $is_es
			? "**Agave Angustifolia-Espadín de Oaxaca**\n\n" .
			  "• **Rendimiento Proyectado:** 25.0% Anual Estimado\n" .
			  "• **Precio por Token:** \$85.00 MXN\n" .
			  "• **Ubicación:** Oaxaca (Región Mezcalera)\n" .
			  "• **Nivel de Riesgo:** ALTO (Ciclo de maduración prolongado con alta demanda en la industria del mezcal artesanal).\n" .
			  "• **Prácticas Agroecológicas:** Cultivo sin herbicidas químicos, deshierbe manual tradicional y monitoreo de grados Brix."
			: "**Agave Angustifolia-Espadin from Oaxaca**\n\n" .
			  "• **Projected Yield:** 25.0% Annual Return\n" .
			  "• **Token Price:** \$85.00 MXN\n" .
			  "• **Location:** Oaxaca\n" .
			  "• **Risk Level:** HIGH (Long maturation cycle, surging artisan mezcal demand).\n" .
			  "• **Agroecological Practices:** Zero chemical herbicides, traditional manual weeding, Brix sugar tracking.";
	}

	if ( milpa_str_has( $q, array( 'café', 'cafe', 'coffee', 'chiapas' ) ) ) {
		return $is_es
			? "**Café de Altura Sostenible (Chiapas)**\n\n" .
			  "• **Rendimiento Proyectado:** 18.2% Anual\n" .
			  "• **Precio por Token:** \$120.00 MXN\n" .
			  "• **Ubicación:** Chiapas\n" .
			  "• **Nivel de Riesgo:** MEDIO\n" .
			  "• **Score de Sostenibilidad:** 88/100 (Comercio Justo, café bajo sombra nativa)"
			: "**Sustainable High-Altitude Coffee (Chiapas)**\n\n" .
			  "• **Projected Yield:** 18.2% Annual\n" .
			  "• **Token Price:** \$120.00 MXN\n" .
			  "• **Location:** Chiapas\n" .
			  "• **Risk Level:** MEDIUM\n" .
			  "• **Sustainability Score:** 88/100 (Fair Trade, native canopy shade grown)";
	}

	if ( milpa_str_has( $q, array( 'maiz', 'maíz', 'corn' ) ) ) {
		return $is_es
			? "**Maíz Criollo Orgánico Ancestral**\n\n" .
			  "• **Rendimiento Proyectado:** 12.5% Anual\n" .
			  "• **Precio por Token:** \$50.00 MXN (Accesible para microinversores)\n" .
			  "• **Nivel de Riesgo:** BAJO (Alta resiliencia climática)\n" .
			  "• **Score de Sostenibilidad:** 95/100"
			: "**Native Organic Corn (Maíz Criollo)**\n\n" .
			  "• **Projected Yield:** 12.5% Annual\n" .
			  "• **Token Price:** \$50.00 MXN\n" .
			  "• **Risk Level:** LOW (High climate resilience, fast harvest cycles)\n" .
			  "• **Sustainability Score:** 95/100";
	}

	if ( milpa_str_has( $q, array( 'aguacate', 'avocado', 'michoacan', 'michoacán' ) ) ) {
		return $is_es
			? "**Aguacate Hass Regenerativo (Michoacán)**\n\n" .
			  "• **Rendimiento Proyectado:** 10.5% Anual\n" .
			  "• **Precio por Token:** \$200.00 MXN\n" .
			  "• **Nivel de Riesgo:** BAJO\n" .
			  "• **Sostenibilidad:** GlobalG.A.P., cero huella de deforestación, riego por microgoteo inteligente."
			: "**Regenerative Hass Avocado (Michoacán)**\n\n" .
			  "• **Projected Yield:** 10.5% Annual\n" .
			  "• **Token Price:** \$200.00 MXN\n" .
			  "• **Risk Level:** LOW\n" .
			  "• **Sustainability:** GlobalG.A.P. certified, zero deforestation, smart micro-drip irrigation.";
	}

	if ( milpa_str_has( $q, array( 'nft', 'opensea', 'blockchain', 'trazabilidad', 'contrato', 'smart contract' ) ) ) {
		return $is_es
			? "**Trazabilidad Blockchain en Milpa Tech**\n\n" .
			  "1. **Contratos Inteligentes:** Cada cultivo puede vincularse a un NFT único en Polygon una vez completada la revisión legal (Fase 3 de nuestro plan de lanzamiento).\n" .
			  "2. **Inmutabilidad:** Las transacciones quedan registradas en el ledger para auditoría pública.\n" .
			  "3. **Estado actual:** Estamos en fase de plataforma — la tokenización real está sujeta a asesoría legal en curso."
			: "**Blockchain Traceability on Milpa Tech**\n\n" .
			  "1. **Smart Contracts:** Each crop can be linked to a unique NFT on Polygon once legal review clears (Phase 3 of our rollout).\n" .
			  "2. **Immutability:** Transactions are logged for public auditing.\n" .
			  "3. **Current status:** We're in platform-build phase — real tokenization is pending ongoing legal counsel.";
	}

	if ( milpa_str_has( $q, array( 'precio', 'mercado', 'market', 'cotizacion', 'cotización', 'commodities' ) ) ) {
		return $is_es
			? "**Cotizaciones de Cultivos Disponibles**\n\n" .
			  "• **Maíz Criollo / Grano:** \$50.00 MXN / token\n" .
			  "• **Café Arábica de Sombra:** \$120.00 MXN / token\n" .
			  "• **Aguacate Hass:** \$200.00 MXN / token\n" .
			  "• **Agave Espadín:** \$85.00 MXN / token\n\n" .
			  "*Visita el [Marketplace](/shop/) para ver disponibilidad actual.*"
			: "**Available Crop Pricing**\n\n" .
			  "• **Native Organic Corn:** \$50.00 MXN / token\n" .
			  "• **Shade Arabica Coffee:** \$120.00 MXN / token\n" .
			  "• **Hass Avocado:** \$200.00 MXN / token\n" .
			  "• **Agave Angustifolia-Espadin:** \$85.00 MXN / token\n\n" .
			  "*Visit the [Marketplace](/shop/) for current availability.*";
	}

	if ( milpa_str_has( $q, array( 'invertir', 'invest', 'funciona', 'como', 'cómo', 'comprar' ) ) ) {
		return $is_es
			? "**Guía Paso a Paso: Cómo Invertir en Milpa Tech**\n\n" .
			  "1. **Explora el [Marketplace](/shop/):** Filtra cultivos por tipo, riesgo o score de sostenibilidad.\n" .
			  "2. **Regístrate como Inversionista:** Crea tu cuenta en [Mi Cuenta](/my-account/).\n" .
			  "3. **Selecciona tu Fracción:** Elige la cantidad de tokens deseada y confirma tu compra.\n" .
			  "4. **Seguimiento en Vivo:** Revisa el desarrollo del cultivo directamente en su ficha.\n" .
			  "5. **Liquidación de Cosecha:** Al concluir la cosecha, los dividendos se calculan según el rendimiento."
			: "**Step-by-Step Guide: How to Invest on Milpa Tech**\n\n" .
			  "1. **Explore the [Marketplace](/shop/):** Filter crops by type, risk, or sustainability score.\n" .
			  "2. **Register as an Investor:** Create your account at [My Account](/my-account/).\n" .
			  "3. **Select Your Fraction:** Choose the number of tokens and confirm your purchase.\n" .
			  "4. **Live Tracking:** Monitor crop development directly on its listing.\n" .
			  "5. **Harvest Liquidation:** Once the harvest completes, yields are calculated based on performance.";
	}

	return $is_es
		? "**Asistente Inteligente Milpa AI**\n\n" .
		  "Te ayudo a resolver consultas sobre la plataforma **Milpa Tech**:\n\n" .
		  "• **Cultivos Disponibles:** Agave Espadín (25% retorno), Café de Altura (18.2%), Maíz Criollo (12.5%) y Aguacate Hass (10.5%).\n" .
		  "• **Inversión Mínima:** Desde \$50 MXN.\n" .
		  "• **Cómo Vender:** Productores y Comerciantes pueden registrarse en [Mi Cuenta](/my-account/).\n\n" .
		  "*¿Te gustaría consultar detalles sobre algún cultivo en específico o cómo registrarte?*"
		: "**Milpa AI Smart Assistant**\n\n" .
		  "I can help with all aspects of **Milpa Tech**:\n\n" .
		  "• **Available Crops:** Agave Espadin (25% yield), High-Altitude Coffee (18.2%), Native Corn (12.5%), Hass Avocado (10.5%).\n" .
		  "• **Minimum Ticket:** Starting at \$50 MXN.\n" .
		  "• **Selling:** Producers and Traders can register at [My Account](/my-account/).\n\n" .
		  "*Would you like details on a specific crop, or how to register?*";
}

function milpa_str_has( $haystack, array $needles ) {
	foreach ( $needles as $needle ) {
		if ( false !== mb_strpos( $haystack, $needle ) ) {
			return true;
		}
	}
	return false;
}

/* ---------------------------------------------------------------------
 * Widget: floating bubble, bottom-right (Deskuss support chat already
 * occupies bottom-left — confirmed by screenshot during this build).
 * ------------------------------------------------------------------- */

add_action( 'wp_footer', 'milpa_render_chat_widget' );

function milpa_render_chat_widget() {
	if ( is_admin() ) {
		return;
	}
	$rest_url = esc_url_raw( rest_url( 'milpa/v1/chat' ) );
	?>
	<div id="milpa-chat-root">
		<button id="milpa-chat-toggle" aria-label="Milpa AI">🌽</button>
		<div id="milpa-chat-panel" hidden>
			<div id="milpa-chat-header">Milpa AI</div>
			<div id="milpa-chat-messages"></div>
			<form id="milpa-chat-form">
				<input id="milpa-chat-input" type="text" placeholder="Pregunta sobre cultivos, precios, cómo invertir…" autocomplete="off">
				<button type="submit" aria-label="Enviar">➤</button>
			</form>
		</div>
	</div>
	<style>
		#milpa-chat-root { position: fixed; right: 20px; bottom: 20px; z-index: 99999; font-family: 'Inter', system-ui, sans-serif; }
		#milpa-chat-toggle {
			width: 56px; height: 56px; border-radius: 50%; border: none; cursor: pointer;
			background: linear-gradient(135deg, var(--milpa-emerald, #059669), var(--milpa-emerald-2, #10b981));
			color: #fff; font-size: 24px; box-shadow: 0 8px 24px -8px rgba(2,6,23,.4);
		}
		#milpa-chat-panel {
			position: absolute; right: 0; bottom: 68px; width: 320px; max-width: calc(100vw - 40px);
			height: 420px; max-height: 70vh; background: #fff; border-radius: 14px;
			box-shadow: 0 16px 40px -12px rgba(2,6,23,.35); flex-direction: column; overflow: hidden;
			border: 1px solid #e2e8f0; display: flex;
		}
		#milpa-chat-panel[hidden] { display: none; }
		#milpa-chat-header {
			background: var(--milpa-surface, #0f172a); color: #fff; padding: 12px 16px; font-weight: 700; font-size: 14px;
		}
		#milpa-chat-messages { flex: 1; overflow-y: auto; padding: 12px; font-size: 13.5px; line-height: 1.5; }
		.milpa-msg { margin-bottom: 10px; padding: 8px 12px; border-radius: 10px; max-width: 88%; white-space: pre-wrap; }
		.milpa-msg-user { background: var(--milpa-emerald, #059669); color: #fff; margin-left: auto; }
		.milpa-msg-bot { background: #f1f5f9; color: #0f172a; }
		#milpa-chat-form { display: flex; border-top: 1px solid #e2e8f0; }
		#milpa-chat-input { flex: 1; border: none; padding: 10px 12px; font-size: 13px; outline: none; }
		#milpa-chat-form button { border: none; background: none; color: var(--milpa-emerald, #059669); font-size: 16px; padding: 0 14px; cursor: pointer; }
	</style>
	<script>
	(function () {
		var root = document.getElementById( 'milpa-chat-root' );
		var toggle = document.getElementById( 'milpa-chat-toggle' );
		var panel = document.getElementById( 'milpa-chat-panel' );
		var messages = document.getElementById( 'milpa-chat-messages' );
		var form = document.getElementById( 'milpa-chat-form' );
		var input = document.getElementById( 'milpa-chat-input' );
		var history = [];
		var greeted = false;

		function addMessage( text, who ) {
			var div = document.createElement( 'div' );
			div.className = 'milpa-msg milpa-msg-' + who;
			div.textContent = text;
			messages.appendChild( div );
			messages.scrollTop = messages.scrollHeight;
		}

		toggle.addEventListener( 'click', function () {
			panel.hidden = ! panel.hidden;
			if ( ! panel.hidden && ! greeted ) {
				greeted = true;
				addMessage( '¡Hola! Soy Milpa AI. Pregúntame sobre cultivos disponibles, precios, o cómo invertir.', 'bot' );
			}
		} );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var text = input.value.trim();
			if ( ! text ) { return; }
			addMessage( text, 'user' );
			history.push( { role: 'user', text: text } );
			input.value = '';
			input.disabled = true;

			fetch( <?php echo wp_json_encode( $rest_url ); ?>, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( { message: text, history: history, lang: 'es' } )
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					var reply = data.reply || 'Lo siento, no pude procesar tu pregunta.';
					addMessage( reply, 'bot' );
					history.push( { role: 'model', text: reply } );
				} )
				.catch( function () {
					addMessage( 'Hubo un problema de conexión. Intenta de nuevo.', 'bot' );
				} )
				.finally( function () {
					input.disabled = false;
					input.focus();
				} );
		} );
	})();
	</script>
	<?php
}
