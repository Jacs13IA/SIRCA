const chat = document.querySelector('[data-citizen-chat]');

if (chat) {
	const messageInput = chat.querySelector('[data-chat-message]');
	const sendButton = chat.querySelector('[data-chat-send]');
	const chatForm = chat.querySelector('[data-chat-form]');
	const chatStatus = chat.querySelector('[data-chat-status]');
	const messages = chat.querySelector('.citizen-chat__messages');
	const evidenceInput = chat.querySelector('[data-evidence-input]');
	const evidenceTrigger = chat.querySelector('[data-evidence-trigger]');
	const evidenceRemove = chat.querySelector('[data-evidence-remove]');
	const evidencePreview = chat.querySelector('[data-evidence-preview]');
	const evidenceImage = chat.querySelector('[data-evidence-image]');
	const evidenceName = chat.querySelector('[data-evidence-name]');
	const evidenceSize = chat.querySelector('[data-evidence-size]');
	const evidenceStatus = chat.querySelector('[data-evidence-status]');
	const locationTrigger = chat.querySelector('[data-location-trigger]');
	const locationStatus = chat.querySelector('[data-location-status]');
	const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
	const allowedEvidence = {
		jpg: 'image/jpeg',
		jpeg: 'image/jpeg',
		png: 'image/png',
		pdf: 'application/pdf',
	};
	const maxEvidenceSize = 10 * 1024 * 1024;
	let isBusy = false;
	let isUploadingEvidence = false;
	let previewUrl = null;
	setEvidenceRequested(chat.dataset.evidenceRequested === 'true');

	messageInput.addEventListener('input', updateSendButton);

	chatForm.addEventListener('submit', (event) => {
		event.preventDefault();
		const message = messageInput.value.trim();

		if (message) {
			void sendTurn(message, {}, message);
		}
	});

	evidenceTrigger.addEventListener('click', () => evidenceInput.click());

	evidenceInput.addEventListener('change', () => {
		const file = evidenceInput.files?.[0];

		if (file) {
			void validateAndUploadEvidence(file);
		}
	});

	evidenceRemove.addEventListener('click', () => {
		evidenceInput.value = '';
		clearEvidencePreview();
		setStatus(evidenceStatus, 'El agente solicitará evidencia si la necesita.');
	});

	locationTrigger.addEventListener('click', () => {
		if (isBusy || isUploadingEvidence) {
			return;
		}

		if (!window.isSecureContext || !navigator.geolocation) {
			setStatus(locationStatus, 'La ubicación requiere un navegador compatible y una conexión segura (HTTPS).', 'error');
			return;
		}

		locationTrigger.disabled = true;
		setStatus(locationStatus, 'Solicitando permiso para obtener la ubicación...', 'pending');

		navigator.geolocation.getCurrentPosition(
			({ coords }) => {
				setStatus(locationStatus, 'Ubicación obtenida. Enviándola al asistente...', 'pending');
				void sendTurn('Te comparto mi ubicación actual.', {
					latitude: coords.latitude,
					longitude: coords.longitude,
				}, 'Te comparto mi ubicación actual.');
			},
			(error) => {
				locationTrigger.disabled = false;
				const messagesByCode = {
					1: 'No se concedió permiso para acceder a la ubicación.',
					2: 'El dispositivo no pudo determinar la ubicación.',
					3: 'La solicitud de ubicación tardó demasiado. Inténtalo de nuevo.',
				};
				setStatus(locationStatus, messagesByCode[error.code] ?? 'No fue posible obtener la ubicación.', 'error');
			},
			{ enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
		);
	});

	async function sendTurn(message, context, visibleMessage) {
		if (isBusy || isUploadingEvidence) {
			return;
		}

		setBusy(true);
		setStatus(chatStatus, '');
		const typing = appendMessage('assistant', 'Escribiendo...', true);

		try {
			const response = await fetch(chat.dataset.messageUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: requestHeaders(true),
				body: JSON.stringify({ message, ...context }),
			});
			const payload = await readResponse(response);

			typing.remove();
			appendMessage('user', visibleMessage);
			if (payload.data.message) {
				appendMessage('assistant', payload.data.message);
			}

			setEvidenceRequested(payload.data.evidence_requested === true);
			if (payload.data.evidence_pending === true && payload.data.delivery_pending !== true) {
				setStatus(evidenceStatus, 'Archivo compartido con el agente; aún no confirma que se haya asociado al reporte.', 'pending');
			}
			if (context.latitude !== undefined) {
				setStatus(locationStatus, 'Ubicación compartida con el asistente.', 'success');
			}
			messageInput.value = '';
			setStatus(chatStatus, '');
		} catch (error) {
			typing.remove();
			setStatus(chatStatus, error.message || 'No se pudo conectar. Inténtalo de nuevo.', 'error');
		} finally {
			setBusy(false);
			locationTrigger.disabled = false;
		}
	}

	async function validateAndUploadEvidence(file) {
		const extension = file.name.split('.').pop()?.toLowerCase();
		const expectedMimeType = allowedEvidence[extension];

		clearEvidencePreview();
		if (!expectedMimeType || (file.type && file.type !== expectedMimeType)) {
			setStatus(evidenceStatus, 'Formato no permitido. Selecciona JPG, JPEG, PNG o PDF.', 'error');
			evidenceInput.value = '';
			return;
		}

		if (file.size > maxEvidenceSize) {
			setStatus(evidenceStatus, 'El archivo supera el límite de 10 MB.', 'error');
			evidenceInput.value = '';
			return;
		}

		showEvidencePreview(file);
		isUploadingEvidence = true;
		evidenceTrigger.disabled = true;
		evidenceInput.disabled = true;
		setStatus(evidenceStatus, 'Subiendo evidencia de forma segura...', 'pending');
		const typing = appendMessage('assistant', 'Procesando evidencia...', true);

		try {
			const formData = new FormData();
			formData.append('evidence', file);
			const response = await fetch(chat.dataset.evidenceUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: requestHeaders(false),
				body: formData,
			});
			const payload = await readResponse(response);

			typing.remove();
			appendMessage('user', 'Adjunté la evidencia solicitada.');
			if (payload.data.message) {
				appendMessage('assistant', payload.data.message);
			}
			setEvidenceRequested(payload.data.evidence_requested === true);
			if (payload.data.delivery_pending) {
				setStatus(evidenceStatus, 'Archivo recibido y protegido; se compartirá cuando el agente vuelva a responder.', 'pending');
			} else {
				setStatus(evidenceStatus, payload.data.evidence_pending
					? 'Archivo compartido con el agente; aún no confirma que se haya asociado al reporte.'
					: 'Archivo recibido por el agente.', 'success');
			}
			clearEvidencePreview();
			evidenceInput.value = '';
		} catch (error) {
			typing.remove();
			setStatus(evidenceStatus, error.message || 'No se pudo enviar la evidencia. Inténtalo de nuevo.', 'error');
		} finally {
			isUploadingEvidence = false;
			const requested = evidenceTrigger.dataset.requested === 'true';
			evidenceTrigger.disabled = !requested || isBusy;
			evidenceInput.disabled = !requested || isBusy;
			updateSendButton();
		}
	}

	function requestHeaders(json) {
		const headers = {
			Accept: 'application/json',
			'X-CSRF-TOKEN': csrfToken,
		};

		if (json) {
			headers['Content-Type'] = 'application/json';
		}

		return headers;
	}

	async function readResponse(response) {
		const payload = await response.json();

		if (!response.ok || payload.success !== true || !payload.data) {
			throw new Error(payload.message || 'No fue posible completar la solicitud.');
		}

		return payload;
	}

	function setEvidenceRequested(requested) {
		evidenceTrigger.dataset.requested = requested ? 'true' : 'false';
		evidenceTrigger.disabled = !requested || isUploadingEvidence || isBusy;
		evidenceInput.disabled = !requested || isUploadingEvidence || isBusy;

		if (!requested && !isUploadingEvidence && evidenceStatus.dataset.state !== 'error') {
			setStatus(evidenceStatus, 'El agente solicitará evidencia si la necesita.');
		}
	}

	function setBusy(busy) {
		isBusy = busy;
		messageInput.disabled = busy;
		locationTrigger.disabled = busy;
		evidenceTrigger.disabled = busy || evidenceTrigger.dataset.requested !== 'true' || isUploadingEvidence;
		evidenceInput.disabled = busy || evidenceTrigger.dataset.requested !== 'true' || isUploadingEvidence;
		updateSendButton();
	}

	function updateSendButton() {
		sendButton.disabled = isBusy || isUploadingEvidence || messageInput.value.trim().length === 0;
	}

	function showEvidencePreview(file) {
		evidenceName.textContent = file.name;
		evidenceSize.textContent = formatFileSize(file.size);
		evidencePreview.hidden = false;

		if (file.type.startsWith('image/')) {
			previewUrl = URL.createObjectURL(file);
			evidenceImage.src = previewUrl;
			evidenceImage.hidden = false;
		}
	}

	function clearEvidencePreview() {
		if (previewUrl) {
			URL.revokeObjectURL(previewUrl);
			previewUrl = null;
		}

		evidenceImage.removeAttribute('src');
		evidenceImage.hidden = true;
		evidencePreview.hidden = true;
		evidenceName.textContent = '';
		evidenceSize.textContent = '';
	}

	function appendMessage(role, text, isPending = false) {
		const article = document.createElement('article');
		article.className = `citizen-chat__message citizen-chat__message--${role}`;
		if (isPending) {
			article.dataset.pending = 'true';
		}

		const avatar = document.createElement('span');
		avatar.className = 'citizen-chat__avatar';
		avatar.setAttribute('aria-hidden', 'true');
		avatar.textContent = role === 'assistant' ? 'S' : 'T';

		const body = document.createElement('div');
		const sender = document.createElement('p');
		sender.className = 'citizen-chat__sender';
		sender.textContent = role === 'assistant' ? 'SIRCA' : 'Tú';

		const bubble = document.createElement('div');
		bubble.className = 'citizen-chat__bubble';
		const paragraph = document.createElement('p');
		paragraph.textContent = text;
		bubble.append(paragraph);
		body.append(sender, bubble);
		article.append(avatar, body);
		messages.append(article);
		article.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

		return article;
	}

	function setStatus(element, message, state = '') {
		element.textContent = message;
		element.dataset.state = state;
	}

	function formatFileSize(bytes) {
		return bytes < 1024 * 1024
			? `${Math.max(1, Math.round(bytes / 1024))} KB`
			: `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
	}
}