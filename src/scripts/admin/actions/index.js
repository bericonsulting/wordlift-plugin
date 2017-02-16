/**
 * Actions.
 *
 * Define the list of actions for the app.
 *
 * @since 3.11.0
 */

/**
 * Internal dependencies
 */
import * as types from '../constants/ActionTypes';

/**
 * The `receiveAnalysisResults` action is dispatched when new analysis results
 * are received.
 *
 * @since 3.11.0
 * @param {{entities: (Array)}} results The analysis results.
 * @return {Function} The action's function.
 */
export const receiveAnalysisResults = ( results ) => (
	{ type: types.RECEIVE_ANALYSIS_RESULTS, results }
);

export const setCurrentEntity = ( entity ) => (
	{ type: types.SET_CURRENT_ENTITY, entity }
);

/**
 * The `toggleEntity` action toggles the selection flag for the provided entity.
 *
 * @since 3.11.0
 * @param {Object} entity The entity being toggled.
 * @return {Function} The action's function.
 */
export const toggleEntity = entity => (
	{ type: types.TOGGLE_ENTITY, entity }
);

/**
 * The `toggleLink` action is dispatched to enable/disable linking an entity.
 *
 * @since 3.11.0
 * @param {Object} entity The entity.
 * @return {Function} The action's function.
 */
export const toggleLink = ( entity ) => (
	{ type: types.TOGGLE_LINK, entity }
);

/**
 * The `updateOccurrencesForEntity` action is dispatched when the number of
 * occurrences for an entity is updated (by the legacy Angular application).
 *
 * @since 3.11.0
 *
 * @param {string} entityId The entity unique id.
 * @param {Array} occurrences An array of occurrences.
 * @return {Function} The action's function.
 */
export const updateOccurrencesForEntity = ( entityId, occurrences ) => (
	{ type: types.UPDATE_OCCURRENCES_FOR_ENTITY, entityId, occurrences }
);
