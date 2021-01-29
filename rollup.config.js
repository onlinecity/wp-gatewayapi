import babel from '@rollup/plugin-babel';
import { nodeResolve } from '@rollup/plugin-node-resolve';
import { terser } from 'rollup-plugin-terser';
import filesize from 'rollup-plugin-filesize';
import progress from 'rollup-plugin-progress';
import visualizer from 'rollup-plugin-visualizer';

export default {
    input: 'js/main.js',
    output: [
        {
            file: 'dist/main.js',
            format: 'cjs',
        },
    ],
    plugins: [
        terser(),
        babel({ babelHelpers: 'bundled' }),
        nodeResolve(),
        // All of following are just for beautification, not required for bundling purpose
        progress(),
        visualizer(),
        filesize(),
    ],
};