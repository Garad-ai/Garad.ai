import * as THREE from "three";
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";

const container = document.getElementById("avatar3d");
const scene = new THREE.Scene();

const camera = new THREE.PerspectiveCamera(
    45,
    container.clientWidth / container.clientHeight,
    0.1,
    1000
);

camera.position.set(0, 1.5, 12);
camera.lookAt(0, 1.0, 0);

const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
renderer.setSize(container.clientWidth, container.clientHeight);
renderer.setPixelRatio(window.devicePixelRatio);
container.appendChild(renderer.domElement);

const light = new THREE.HemisphereLight(0xffffff, 0x444444, 3);
scene.add(light);

let avatar = null;
let mouthMesh = null;
let originalAvatarY = 0; 
let originalMouthY = 0;

const loader = new GLTFLoader();

loader.load(
    "/speaker/models/avatar.glb",
    (gltf) => {
        avatar = gltf.scene;
        scene.add(avatar);

        // 1. Cusbooneysii matrix-ka si xisaabtu u saxnaato
        avatar.updateMatrixWorld(true);

        // 2. Xisaabi cabbirka adigoo eegaya KALIYA mesh-yada (ka saar bones-ka iyo qaladka zoom-ka)
        const box = new THREE.Box3();
        avatar.traverse((child) => {
            if (child.isMesh) {
                box.expandByObject(child);
            }
        });

        const center = box.getCenter(new THREE.Vector3());
        const size = box.getSize(new THREE.Vector3());

        if (size.y === 0) size.y = 1;

        avatar.position.sub(center);

        // 3. Deji cabbirka saxda ah
        const targetHeight = 3.5; 
        const scaleFactor = targetHeight / size.y;
        avatar.scale.set(scaleFactor, scaleFactor, scaleFactor);

        avatar.position.y -= (targetHeight / 6);
        avatar.rotation.set(0, 0, 0);

        originalAvatarY = avatar.position.y; 

        avatar.traverse((child) => {
            if (child.isMesh) {
                child.castShadow = true;
                child.receiveShadow = true;

                const name = child.name.toLowerCase();
                if (
                    name.includes("mouth") ||
                    name.includes("jaw") ||
                    name.includes("lip")
                ) {
                    mouthMesh = child;
                    originalMouthY = child.position.y;
                }
            }
        });

        console.log("Avatar Cleaned and Loaded Successfully!");
    },
    undefined,
    (error) => {
        console.error("Failed to load avatar.glb", error);
    }
);

let isSpeaking = false;
window.setAvatarSpeaking = (speaking) => {
    isSpeaking = speaking;
};

function animate() {
    requestAnimationFrame(animate);

    if (avatar && isSpeaking) {
        const t = Date.now() * 0.01;
        if (mouthMesh) {
            // Dhaqdhaqaaqa afka ee xilliga hadalka
            mouthMesh.position.y = originalMouthY + Math.sin(t * 20) * 0.005;
        } 
        // Jirka oo si fudud u dhaqaaqaya inta uu hadlayo
        avatar.position.y = originalAvatarY + Math.sin(t * 5) * 0.02;
        avatar.rotation.y = Math.sin(t * 2) * 0.03;

    } else if (avatar && !isSpeaking) {
        // Dib ugu celi booskii caadiga ahaa marka uu aamuso
        avatar.position.y = originalAvatarY;
        avatar.rotation.y = 0;
        if (mouthMesh) mouthMesh.position.y = originalMouthY;
    }

    renderer.render(scene, camera);
}
animate();

window.addEventListener("resize", () => {
    if(container.clientWidth > 0 && container.clientHeight > 0) {
        camera.aspect = container.clientWidth / container.clientHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(container.clientWidth, container.clientHeight);
    }
});