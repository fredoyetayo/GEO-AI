<?php
/**
 * Fallback implementations for Action Scheduler functions when the library is not available.
 *
 * @package GeoAI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'as_enqueue_async_action' ) ) {
    /**
     * Schedule an asynchronous action using WordPress Cron as a fallback.
     *
     * @param string $hook  The hook to execute.
     * @param array  $args  Optional arguments to pass to the hook.
     * @param string $group Optional action group (unused in fallback).
     * @param bool   $unique Whether the action should be unique.
     *
     * @return int|false Timestamp of the scheduled event on success, false on failure.
     */
    function as_enqueue_async_action( $hook, $args = array(), $group = '', $unique = false ) {
        if ( ! is_array( $args ) ) {
            $args = array( $args );
        }

        $scheduled_args = array_values( $args );

        if ( $unique && function_exists( 'wp_next_scheduled' ) && false !== wp_next_scheduled( $hook, $scheduled_args ) ) {
            return false;
        }

        $timestamp = time() + 1;

        if ( ! function_exists( 'wp_schedule_single_event' ) ) {
            return false;
        }

        return wp_schedule_single_event( $timestamp, $hook, $scheduled_args );
    }
}

if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
    /**
     * Unschedule all actions for a given hook using WordPress Cron as a fallback.
     *
     * @param string $hook  The hook name.
     * @param array  $args  Optional arguments originally used when scheduling the action.
     * @param string $group Optional group (unused in fallback).
     */
    function as_unschedule_all_actions( $hook, $args = array(), $group = '' ) {
        if ( null === $args ) {
            $args = array();
        }

        if ( ! is_array( $args ) ) {
            $args = array( $args );
        }

        $scheduled_args = array_values( $args );

        if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_unschedule_event' ) ) {
            return;
        }

        while ( false !== ( $timestamp = wp_next_scheduled( $hook, $scheduled_args ) ) ) {
            wp_unschedule_event( $timestamp, $hook, $scheduled_args );
        }
    }
}
