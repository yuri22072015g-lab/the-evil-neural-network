const functions = require('firebase-functions');
const admin = require('firebase-admin');
admin.initializeApp();

const db = admin.firestore();

exports.deleteUserCompletely = functions.https.onCall(async (data, context) => {
    // Проверка авторизации
    if (!context.auth) {
        throw new functions.https.HttpsError('unauthenticated', 'Требуется авторизация.');
    }

    const callerUid = context.auth.uid;
    const targetUserId = data.userId;

    if (!targetUserId) {
        throw new functions.https.HttpsError('invalid-argument', 'Не указан userId.');
    }

    // Проверка прав администратора
    const callerDoc = await db.collection('users').doc(callerUid).get();
    if (!callerDoc.exists || callerDoc.data().role !== 'admin') {
        throw new functions.https.HttpsError('permission-denied', 'Недостаточно прав.');
    }

    try {
        // 1. Удаляем документ из Firestore
        await db.collection('users').doc(targetUserId).delete();

        // 2. Удаляем пользователя из Authentication
        await admin.auth().deleteUser(targetUserId);

        return { success: true };
    } catch (error) {
        console.error('Ошибка при удалении:', error);
        throw new functions.https.HttpsError('internal', error.message);
    }
});